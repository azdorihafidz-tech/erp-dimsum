<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy gabungan 4.50 (Kamus Menu + perbaikan audit) + 4.51 (perbaikan A–G) +
 * 4.52 (Stok Opname: opname, berita acara, laporan kerugian, stok rusak, ganti rugi,
 * dashboard & pengingat) + 4.53 (Paket Modul, Masa Langganan & akun Tim IT).
 * Jalankan sekali di SETIAP instalasi (demo dulu, lalu live).
 *  0. SEBELUM membuka link: tambahkan ke .env server baris TEKNISI_NAMA, TEKNISI_EMAIL,
 *     TEKNISI_PASSWORD (min. 12 karakter huruf+angka, BEDA per instalasi) → akun Tim IT pertama.
 *     Hapus ketiga baris itu dari .env setelah deploy berhasil.
 *  1. migrate → 7 migration baru (hanya tabel/kolom baru + akun COA & kategori transaksi baru;
 *     tabel lisensi = semua modul aktif, tanpa batas tanggal → tidak ada yang berubah)
 *  2. Perbaikan450Seeder, Perbaikan451Seeder, StokOpnameSeeder (izin: centangan role bawaan HANYA
 *     untuk izin yang baru dibuat), KamusMenuSeeder — semua idempotent
 *  3. clear & rebuild cache
 *  4. laporan baca-saja (tidak mengubah data)
 *
 * Pengaman: wajib ?confirm=YES-OPNAME452 dan storage/deploy-452.lock belum ada.
 * URL: https://<alamat-erp>/deploy-452.php?confirm=YES-OPNAME452
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-OPNAME452', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-OPNAME452 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-452.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.50 + 4.51 + 4.52 (Kamus Menu, perbaikan audit, Stok Opname) ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'Perbaikan450Seeder', '--force' => true]],
    ['db:seed', ['--class' => 'Perbaikan451Seeder', '--force' => true]],
    ['db:seed', ['--class' => 'StokOpnameSeeder', '--force' => true]],
    ['db:seed', ['--class' => 'PaketLanggananSeeder', '--force' => true]],
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

// 4.53 — akun Tim IT pertama dari .env (dibaca langsung dari file: config bisa sedang di-cache)
if (! $adaError) {
    echo "=== Akun Tim IT ===\n";
    try {
        $env = \Dotenv\Dotenv::parse((string) @file_get_contents(base_path('.env')));
        $nama = trim((string) ($env['TEKNISI_NAMA'] ?? ''));
        $email = strtolower(trim((string) ($env['TEKNISI_EMAIL'] ?? '')));
        $sandi = (string) ($env['TEKNISI_PASSWORD'] ?? '');
        $ada = \App\Models\User::where('role', 'teknisi')->count();
        if ($email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($sandi) < 12 || ! preg_match('/[A-Za-z]/', $sandi) || ! preg_match('/\d/', $sandi)) {
                $adaError = true;
                echo "GAGAL: TEKNISI_EMAIL harus email valid dan TEKNISI_PASSWORD minimal 12 karakter berisi huruf dan angka.\n";
            } elseif ($u = \App\Models\User::where('email', $email)->first()) {
                if ($u->role?->value === 'teknisi') {
                    echo "Akun Tim IT {$email} sudah ada (tidak diubah).\n";
                } else {
                    $adaError = true;
                    echo "GAGAL: email {$email} sudah dipakai akun pelanggan. Pakai email lain untuk Tim IT.\n";
                }
            } else {
                \App\Models\User::create(['name' => $nama ?: 'Tim IT', 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($sandi),
                    'role' => 'teknisi', 'is_active' => true]);
                echo "Akun Tim IT dibuat: {$email} (kata sandi sesuai .env, tidak ditampilkan).\n";
            }
        } elseif ($ada === 0) {
            $adaError = true;
            echo "GAGAL: belum ada akun Tim IT. Tambahkan TEKNISI_NAMA, TEKNISI_EMAIL, TEKNISI_PASSWORD ke .env lalu buka ulang link ini.\n";
        } else {
            echo "{$ada} akun Tim IT sudah ada.\n";
        }
        echo "Paket saat ini: " . \App\Support\Paket::namaPaket() . " (" . count(\App\Support\Paket::data()['modul']) . " modul), langganan: "
            . (\App\Support\Paket::data()['aktif_sampai']?->format('d/m/Y') ?? 'tanpa batas') . ".\n";
    } catch (\Throwable $e) {
        $adaError = true;
        echo 'GAGAL membuat akun Tim IT: ' . $e->getMessage() . "\n";
    }
    echo "\n";
}

$cetak = function (string $judul, callable $isi) {
    echo "=== {$judul} ===\n";
    try {
        $isi();
    } catch (\Throwable $e) {
        echo 'Tidak bisa dibaca: ' . $e->getMessage() . "\n";
    }
    echo "\n";
};

$cetak('Isi Kamus Menu', function () {
    foreach (DB::table('kamus_menus')->selectRaw('kelompok, COUNT(*) as n')->groupBy('kelompok')->pluck('n', 'kelompok') as $k => $n) {
        echo "- {$k}: {$n}\n";
    }
});

$cetak('Stok Opname — centangan izin per role', function () {
    $rows = DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->where('p.name', 'like', 'stok_opname.%')->orderBy('rp.role')->get(['rp.role', 'p.name'])->groupBy('role');
    foreach ($rows as $role => $izin) {
        echo "- {$role}: " . $izin->pluck('name')->map(fn ($n) => str_replace('stok_opname.', '', $n))->implode(', ') . "\n";
    }
    echo "(Owner selalu boleh semua. Role buatan belum dicentang — atur di Role & Hak Akses.)\n";
});

$cetak('Stok Opname — akun COA baru', function () {
    foreach (DB::table('chart_of_accounts')->whereIn('kode', array_keys(\App\Support\StokOpnameAkun::AKUN))->orderBy('kode')->get(['kode', 'nama']) as $a) {
        echo "- {$a->kode} {$a->nama}\n";
    }
});

$cetak('Hosting — fungsi yang dimatikan & kepala lokasi', function () {
    $mati = trim((string) ini_get('disable_functions'));
    echo 'disable_functions: ' . ($mati === '' ? '(tidak ada)' : $mati) . "\n";
    echo "Pengingat Stok Opname dipicu saat aplikasi dibuka (tidak butuh cron/proc_open).\n";
    $tanpa = DB::table('cabangs')->whereNull('deleted_at')->where('is_active', true)->whereNull('kepala_cabang_id')->pluck('nama_cabang');
    echo $tanpa->isEmpty() ? "Semua lokasi punya Kepala Cabang.\n"
        : 'Lokasi tanpa Kepala Cabang (pengingat dikirim ke Manajer Cabang lokasi itu + Owner): ' . $tanpa->implode(', ') . "\n";
});

// Laporan BACA SAJA dari 4.50: item tambahan yang mulai memotong stok & akan "Habis" di POS
$cetak('Item Tambahan yang akan "Habis" di POS (stok kosong)', function () {
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
    echo $tambahan->isEmpty() ? "(tidak ada)\n" : "Isi stoknya lewat PO/Penyesuaian Stok, atau (Tambahan Gratis) hilangkan centang Lacak Stok.\n";
});

// Laporan BACA SAJA dari 4.50: barang terhapus (cara lama) yang menyembunyikan baris PO/stok
$cetak('Barang terhapus yang ikut menyembunyikan data (cek manual)', function () {
    $rows = DB::table('items as i')->whereNotNull('i.deleted_at')
        ->leftJoin('cabangs as c', 'c.id', '=', 'i.cabang_id')
        ->select('i.id', 'i.kode_item', 'i.nama_item', 'i.deleted_at', 'c.nama_cabang')->get()
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
    echo $rows->isEmpty() ? "(tidak ada)\n" : "Kalau barang di atas masih dipakai: pulihkan di Data Terhapus.\n";
});

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
    echo "Menu baru: Bantuan → Kamus Menu; Stok & Gudang → Stok Opname & Stok Rusak; Laporan → Kerugian Stok; SDM → Ganti Rugi Stok.\n";
    echo "Pengaturan Umum → kartu Stok Opname (hitung buta, batas foto, pengingat).\n";
    echo "Tim IT: login dengan akun dari .env → Pengaturan → Paket & Langganan / Tim IT. Owner: Keamanan → Aktivitas Tim IT.\n";
    echo "HAPUS baris TEKNISI_NAMA/TEKNISI_EMAIL/TEKNISI_PASSWORD dari .env sekarang.\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-452.php DARI SERVER SEKARANG ⚠️\n";
