<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.50 + 4.51 (Kamus Menu + perbaikan temuan audit) di hosting tanpa terminal. Jalankan sekali di SETIAP
 * instalasi (D'mentai live & demo). Script ini:
 *  1. migrate → 3 migration baru: tabel kamus_menus, items.jenis_olahan_id, penggajians (potongan telat +
 *     kas pembayaran). Hanya kolom/tabel baru — data lama tidak diubah
 *  2. Perbaikan450Seeder + Perbaikan451Seeder (panduan & tooltip) + KamusMenuSeeder (isi Kamus Menu), idempotent
 *  3. clear & rebuild cache
 *
 * Pengaman: wajib ?confirm=YES-KAMUS450 dan storage/deploy-450.lock belum ada.
 * URL: https://<alamat-erp>/deploy-450.php?confirm=YES-KAMUS450
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-KAMUS450', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-KAMUS450 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-450.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.50 — Kamus Menu ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'Perbaikan450Seeder', '--force' => true]],
    ['db:seed', ['--class' => 'Perbaikan451Seeder', '--force' => true]],
    ['db:seed', ['--class' => 'KamusMenuSeeder', '--force' => true]],
    ['view:clear', []], ['cache:clear', []], ['route:clear', []], ['config:cache', []], ['route:cache', []],
] as [$perintah, $opsi]) {
    echo '>>> php artisan ' . $perintah . ' ' . json_encode($opsi) . "\n";
    try {
        Artisan::call($perintah, $opsi);
        echo Artisan::output() . "\n";
    } catch (\Throwable $e) {
        $adaError = true;
        echo 'GAGAL: ' . $e->getMessage() . "\n\n";
        if (in_array($perintah, ['migrate', 'db:seed'], true)) {
            echo "Berhenti — langkah berikutnya tidak dijalankan.\n";
            break;
        }
    }
}

try {
    $perKelompok = DB::table('kamus_menus')->selectRaw('kelompok, COUNT(*) as n')->groupBy('kelompok')->pluck('n', 'kelompok');
    echo "=== Isi Kamus Menu ===\n";
    foreach ($perKelompok as $k => $n) {
        echo "- {$k}: {$n}\n";
    }
    echo "\n";
} catch (\Throwable $e) {
    echo 'Tidak bisa membaca kamus_menus: ' . $e->getMessage() . "\n\n";
}

// Laporan BACA SAJA: item tambahan yang mulai sekarang memotong stok & akan bertanda "Habis"
// di POS karena stoknya kosong. Isi stok (PO/Penyesuaian) atau hilangkan centang Lacak Stok.
try {
    echo "=== Item Tambahan yang akan \"Habis\" di POS (stok kosong) ===\n";
    $tambahan = DB::table('items as i')->whereNull('i.deleted_at')->where('i.is_active', true)
        ->where(fn ($q) => $q->where(fn ($q) => $q->where('i.tipe', 'tambahan_gratis')->where('i.track_stok', true))
            ->orWhere(fn ($q) => $q->where('i.tipe', 'produk_tambahan')->whereNotExists(fn ($s) => $s->from('resep_bumbu')->whereColumn('resep_bumbu.item_id', 'i.id'))))
        ->leftJoin('cabangs as c', 'c.id', '=', 'i.cabang_id')
        ->select('i.id', 'i.kode_item', 'i.nama_item', 'i.tipe', 'i.cabang_id', 'c.nama_cabang')->get()
        ->filter(fn ($i) => (float) DB::table('stocks')->where('item_id', $i->id)->whereNull('deleted_at')
            ->when($i->cabang_id, fn ($q) => $q->where('lokasi_id', $i->cabang_id))->sum('qty') <= 0);
    foreach ($tambahan as $i) {
        echo "- [{$i->kode_item}] {$i->nama_item} (" . ($i->tipe === 'tambahan_gratis' ? 'Tambahan Gratis' : 'Produk Tambahan') . ', ' . ($i->nama_cabang ?? 'semua cabang') . ")\n";
    }
    echo $tambahan->isEmpty() ? "(tidak ada)\n\n" : "Isi stoknya lewat PO/Penyesuaian Stok, atau (Tambahan Gratis) hilangkan centang Lacak Stok di Bahan Baku & Kemasan.\n\n";
} catch (\Throwable $e) {
    echo 'Tidak bisa membaca item tambahan: ' . $e->getMessage() . "\n\n";
}

// Laporan BACA SAJA: barang yang dulu dihapus (cara lama) dan ikut menyembunyikan baris PO
// yang PO-nya masih ada, atau stok yang masih bernilai. Tidak diubah otomatis —
// pulihkan lewat Keamanan → Data Terhapus → Items kalau barang itu masih dipakai.
try {
    echo "=== Barang terhapus yang ikut menyembunyikan data (cek manual) ===\n";
    $rows = DB::table('items as i')->whereNotNull('i.deleted_at')
        ->leftJoin('cabangs as c', 'c.id', '=', 'i.cabang_id')
        ->select('i.id', 'i.kode_item', 'i.nama_item', 'i.deleted_at', 'c.nama_cabang')
        ->get()
        ->map(function ($i) {
            $po = DB::table('purchase_order_items as poi')->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->where('poi.item_id', $i->id)->where('poi.deleted_at', $i->deleted_at)->whereNull('po.deleted_at')->count();
            $stok = (float) DB::table('stocks')->where('item_id', $i->id)->where('deleted_at', $i->deleted_at)->sum('qty');
            return [$i, $po, $stok];
        })
        ->filter(fn ($r) => $r[1] > 0 || $r[2] > 0);
    foreach ($rows as [$i, $po, $stok]) {
        echo "- [{$i->kode_item}] {$i->nama_item} ({$i->nama_cabang}), dihapus {$i->deleted_at}: {$po} baris PO, stok tersembunyi " . rtrim(rtrim(number_format($stok, 3, ',', '.'), '0'), ',') . "\n";
    }
    echo $rows->isEmpty() ? "(tidak ada)\n\n" : "Kalau barang di atas masih dipakai: pulihkan di Data Terhapus (baris PO & stoknya ikut kembali).\n\n";
} catch (\Throwable $e) {
    echo 'Tidak bisa membaca data barang terhapus: ' . $e->getMessage() . "\n\n";
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
    echo "Menu baru: Bantuan → Kamus Menu, dan tombol \"Kamus\" di bagian atas setiap halaman.\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-450.php DARI SERVER SEKARANG ⚠️\n";
