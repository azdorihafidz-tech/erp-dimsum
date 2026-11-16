<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Memperbaiki data yang tercatat dengan JAM SERVER SALAH (+7 jam) pada
 * 16 Nov 2026 sekitar pukul 22.02–22.47 WIB. Selama ±40 menit itu jam server
 * hosting melompat 7 jam ke depan, sehingga transaksi tercatat 17 Nov pukul
 * 05.07–05.32 (padahal sebenarnya 16 Nov pukul 22.07–22.32).
 *
 * Yang dilakukan:
 *  - semua kolom tanggal-jam yang berada di jendela 17 Nov 05.00–05.40 → dikurangi 7 jam
 *  - kolom tanggal (tanggal order, tanggal transaksi kas, tanggal masuk batch) baris
 *    yang dibuat di jendela itu → 17 Nov menjadi 16 Nov
 *  - hanya baris yang SUDAH ADA saat salinan database diperiksa (batas ID per tabel),
 *    jadi transaksi baru yang dibuat sesudahnya tidak tersentuh
 *  - semua dalam 1 transaksi database; cache dibersihkan
 *
 * Tahap 1 (pratinjau, tidak mengubah apa pun):
 *   https://<alamat-erp>/perbaiki-jam-1611.php?confirm=YES-JAM1611
 * Tahap 2 (jalankan):
 *   https://<alamat-erp>/perbaiki-jam-1611.php?confirm=YES-JAM1611&tahap=jalankan
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const JAM_AWAL = '2026-11-17 05:00:00';
const JAM_AKHIR = '2026-11-17 05:40:00';
const TGL_SALAH = '2026-11-17';
const TGL_BENAR = '2026-11-16';

// ID terbesar tiap tabel saat database live diekspor (17 Nov ±00.55 WIB).
// Baris dengan ID lebih besar = dibuat sesudahnya → tidak disentuh.
// null = tabel tanpa ID angka (cukup dibatasi jendela jam).
const BATAS_ID = [
    'activity_log' => 126845,
    'kas' => 14,
    'notifications' => null,
    'orders' => 4489,
    'order_histories' => 4785,
    'order_item_histories' => 6723,
    'order_payments' => 4449,
    'stocks' => 270,
    'stock_batches' => 1993,
    'stock_histories' => 42711,
    'stock_movements' => 42752,
    'transaksi_keuangans' => 4450,
];

if (! isset($_GET['confirm']) || ! hash_equals('YES-JAM1611', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-JAM1611 di URL.\n";
    exit;
}
$jalankan = ($_GET['tahap'] ?? '') === 'jalankan';
$lock = storage_path('perbaiki-jam-1611.lock');
if ($jalankan && file_exists($lock)) {
    http_response_code(403);
    echo "DITOLAK: sudah pernah dijalankan ({$lock} ada).\n";
    exit;
}

$fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

echo "=== Perbaikan jam server salah 16 Nov 2026 ===\n";
echo 'Database      : ' . DB::getDatabaseName() . "\n";
echo 'Jam aplikasi  : ' . now()->format('Y-m-d H:i:s') . ' (' . config('app.timezone') . ")\n";
echo 'Jam MySQL     : ' . DB::selectOne('select now() n')->n . "\n";
echo "(Kalau kedua jam di atas tidak sesuai jam sebenarnya, jam server masih salah — hubungi hosting dulu.)\n\n";

// ---------- 1. Penjaga: order di jendela harus benar-benar "bergeser" ----------
$orders = DB::table('orders')->whereBetween('created_at', [JAM_AWAL, JAM_AKHIR])
    ->where('id', '<=', BATAS_ID['orders'])->orderBy('id')
    ->get(['id', 'nomor_order', 'cabang_id', 'tanggal_order', 'status', 'total_bayar', 'nomor_antrian', 'created_at']);
$namaCabang = DB::table('cabangs')->pluck('nama_cabang', 'id');

if ($orders->isEmpty()) {
    echo "Tidak ada order yang tercatat di jendela jam salah. Tidak ada yang perlu diperbaiki.\n";
    echo "\n⚠️ HAPUS FILE perbaiki-jam-1611.php DARI SERVER ⚠️\n";
    exit;
}

$tidakBergeser = [];
foreach ($orders as $o) {
    // Order bergeser pasti punya order dengan ID lebih besar yang jamnya masih 16 Nov.
    $adaSesudah = DB::table('orders')->where('id', '>', $o->id)
        ->where('created_at', '<', TGL_SALAH . ' 00:00:00')->exists();
    if (! $adaSesudah) {
        $tidakBergeser[] = $o->nomor_order;
    }
}
if ($tidakBergeser) {
    http_response_code(409);
    echo "DIBATALKAN: order berikut berada di jendela jam salah tetapi tidak terbukti bergeser:\n  "
        . implode(', ', $tidakBergeser) . "\nHubungi pengembang sebelum melanjutkan.\n";
    exit;
}

// Nomor antrian: saat jam salah, antrian dimulai lagi dari 1 ("hari baru") dan akan
// bentrok dengan nomor antrian 16 Nov. Beri nomor lanjutan setelah nomor terakhir 16 Nov
// (termasuk order terhapus — sama dengan Order::generateNomorAntrian).
$antrianBaru = [];
$terakhir = [];
foreach ($orders as $o) {
    if ($o->nomor_antrian === null) {
        continue;
    }
    if (! isset($terakhir[$o->cabang_id])) {
        $terakhir[$o->cabang_id] = (int) DB::table('orders')->where('cabang_id', $o->cabang_id)
            ->where('tanggal_order', TGL_BENAR)->max('nomor_antrian');
    }
    $antrianBaru[$o->id] = ++$terakhir[$o->cabang_id];
}

echo "Order yang tanggalnya salah (akan dipindah ke 16 Nov, jam dikurangi 7 jam):\n";
foreach ($orders as $o) {
    $jamBenar = date('H:i:s', strtotime($o->created_at) - 7 * 3600);
    printf("  %-16s %-18s %-11s %10s  tercatat %s → seharusnya %s %s%s\n",
        $o->nomor_order, $namaCabang[$o->cabang_id] ?? $o->cabang_id, $o->status,
        $fmt($o->total_bayar), $o->created_at, TGL_BENAR, $jamBenar,
        isset($antrianBaru[$o->id]) ? " | antrian {$o->nomor_antrian} → {$antrianBaru[$o->id]}" : '');
}
$selesai = $orders->where('status', 'selesai');
echo '  → ' . $selesai->count() . ' order selesai (' . $fmt($selesai->sum('total_bayar')) . ') pindah dari laporan 17 Nov ke 16 Nov; '
    . ($orders->count() - $selesai->count()) . " order dibatalkan.\n\n";

// ---------- 2. Susun perbaikan per tabel ----------
$kolom = DB::select("select table_name t, column_name c, data_type d from information_schema.columns
    where table_schema = database() and data_type in ('datetime','timestamp','date') order by table_name, ordinal_position");
$perTabel = [];
foreach ($kolom as $k) {
    $perTabel[$k->t ?? $k->TABLE_NAME][] = [$k->c ?? $k->COLUMN_NAME, $k->d ?? $k->DATA_TYPE];
}

$rencana = [];
$diluarDaftar = [];
foreach ($perTabel as $tabel => $cols) {
    $jam = array_values(array_filter($cols, fn ($c) => $c[1] !== 'date'));
    $tgl = array_values(array_filter($cols, fn ($c) => $c[1] === 'date'));
    if (! $jam) {
        continue;
    }
    $diJendela = implode(' or ', array_map(fn ($c) => "`{$c[0]}` between ? and ?", $jam));
    $bind = array_merge(...array_fill(0, count($jam), [JAM_AWAL, JAM_AKHIR]));
    $where = "({$diJendela})";
    if (array_key_exists($tabel, BATAS_ID) && BATAS_ID[$tabel] !== null) {
        $where .= ' and `id` <= ' . (int) BATAS_ID[$tabel];
    }
    $jumlah = DB::selectOne("select count(*) n from `{$tabel}` where {$where}", $bind)->n;
    if (! $jumlah) {
        continue;
    }
    if (! array_key_exists($tabel, BATAS_ID)) {
        $diluarDaftar[$tabel] = $jumlah;
        continue;
    }

    // Kolom tanggal dulu (MySQL menilai SET dari kiri ke kanan; created_at masih nilai lama).
    $set = [];
    $setBind = [];
    $punyaCreated = in_array('created_at', array_column($jam, 0), true);
    $rincianTgl = [];
    foreach ($tgl as [$c]) {
        if (! $punyaCreated) {
            continue;
        }
        $set[] = "`{$c}` = if(`{$c}` = ? and `created_at` between ? and ?, ?, `{$c}`)";
        array_push($setBind, TGL_SALAH, JAM_AWAL, JAM_AKHIR, TGL_BENAR);
        $n = DB::selectOne("select count(*) n from `{$tabel}` where `{$c}` = ? and `created_at` between ? and ?"
            . (BATAS_ID[$tabel] !== null ? ' and `id` <= ' . (int) BATAS_ID[$tabel] : ''), [TGL_SALAH, JAM_AWAL, JAM_AKHIR])->n;
        if ($n) {
            $rincianTgl[] = "{$c}: {$n}";
        }
    }
    // Semua kolom jam ditulis eksplisit → kolom ber-ON UPDATE tidak ikut berubah sendiri.
    $rincianJam = [];
    foreach ($jam as [$c]) {
        $set[] = "`{$c}` = if(`{$c}` between ? and ?, `{$c}` - interval 7 hour, `{$c}`)";
        array_push($setBind, JAM_AWAL, JAM_AKHIR);
        $n = DB::selectOne("select count(*) n from `{$tabel}` where `{$c}` between ? and ?"
            . (BATAS_ID[$tabel] !== null ? ' and `id` <= ' . (int) BATAS_ID[$tabel] : ''), [JAM_AWAL, JAM_AKHIR])->n;
        if ($n) {
            $rincianJam[] = "{$c}: {$n}";
        }
    }
    $rencana[$tabel] = ['sql' => "update `{$tabel}` set " . implode(', ', $set) . " where {$where}",
        'bind' => array_merge($setBind, $bind), 'baris' => $jumlah, 'jam' => $rincianJam, 'tgl' => $rincianTgl];
}

echo "Rencana perbaikan per tabel:\n";
foreach ($rencana as $tabel => $r) {
    echo sprintf("  %-22s %4d baris | jam −7: %s", $tabel, $r['baris'], implode(', ', $r['jam']))
        . ($r['tgl'] ? ' | tanggal 17→16: ' . implode(', ', $r['tgl']) : '') . "\n";
}
if ($diluarDaftar) {
    echo "\n⚠️ Tabel lain yang juga punya data di jendela itu (TIDAK disentuh, laporkan ke pengembang):\n";
    foreach ($diluarDaftar as $t => $n) {
        echo "  {$t}: {$n} baris\n";
    }
}

if (! $jalankan) {
    echo "\nIni PRATINJAU — belum ada data yang diubah.\n";
    echo "Kalau sudah sesuai, buka lagi dengan tambahan &tahap=jalankan di URL.\n";
    exit;
}

// ---------- 3. Jalankan ----------
echo "\n>>> Menjalankan perbaikan...\n";
try {
    DB::transaction(function () use ($rencana, $antrianBaru) {
        // Nomor antrian dulu, supaya pemindahan tanggal tidak bentrok dengan orders_antrian_unique.
        foreach ($antrianBaru as $id => $nomor) {
            DB::table('orders')->where('id', $id)->update(['nomor_antrian' => $nomor, 'updated_at' => DB::raw('updated_at')]);
        }
        if ($antrianBaru) {
            echo '  orders: ' . count($antrianBaru) . " nomor antrian disesuaikan\n";
        }
        foreach ($rencana as $tabel => $r) {
            $n = DB::affectingStatement($r['sql'], $r['bind']);
            echo "  {$tabel}: {$n} baris diperbaiki\n";
        }
        $sisa = DB::table('orders')->where('tanggal_order', TGL_SALAH)
            ->whereBetween('created_at', [JAM_AWAL, JAM_AKHIR])->count();
        if ($sisa) {
            throw new RuntimeException("Masih ada {$sisa} order di jendela jam salah — dibatalkan.");
        }
    });
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'GAGAL (tidak ada data yang berubah): ' . $e->getMessage() . "\n";
    exit;
}

foreach (['cache:clear', 'view:clear'] as $cmd) {
    echo ">>> php artisan {$cmd}\n";
    try {
        Artisan::call($cmd);
        echo Artisan::output();
    } catch (\Throwable $e) {
        echo 'Peringatan: ' . $e->getMessage() . "\n";
    }
}

@file_put_contents($lock, now()->toDateTimeString());
echo "\n=== SELESAI — order & transaksi kas di atas sekarang bertanggal 16 Nov ===\n";
echo "\n⚠️ HAPUS FILE perbaiki-jam-1611.php DARI SERVER SEKARANG ⚠️\n";
