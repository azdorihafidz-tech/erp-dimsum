<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.49 (porting erp-coffeeshop: Label Produk gambar, POS HP, Produksi,
 * Kirim ke Cabang, Retur Cabang) di hosting tanpa terminal. Jalankan sekali di
 * SETIAP instalasi (D'mentai live & demo). Script ini:
 *  1. migrate → 5 migration baru (hanya kolom/tabel baru; data lama tidak diubah):
 *     cabangs.label_cup_geser_x/_y, tabel produksi, kolom kiriman di orders +
 *     order_kiriman_items, tabel retur, cabangs.kirim_cabang_aktif (bawaan MATI)
 *  2. Porting449Seeder → 9 izin baru (produksi/kiriman/retur), dicentang untuk
 *     role bawaan HANYA saat izin pertama kali dibuat (centangan Owner tidak
 *     ditimpa), + panduan & tooltip (idempotent)
 *  3. clear & rebuild cache
 *
 * Pengaman: wajib ?confirm=YES-PORTING449 dan storage/deploy-449.lock belum ada.
 * URL: https://<alamat-erp>/deploy-449.php?confirm=YES-PORTING449
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-PORTING449', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-PORTING449 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-449.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.49 — Produksi, Kiriman, Retur, POS HP, Label Gambar ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'Porting449Seeder', '--force' => true]],
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

echo "=== Centangan izin baru per role ===\n";
try {
    $nama = array_column(\Database\Seeders\Porting449Seeder::PERMISSIONS, 'name');
    $rows = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->whereIn('permissions.name', $nama)->orderBy('role')->get(['role_permissions.role', 'permissions.name']);
    foreach ($rows->groupBy('role') as $role => $izin) {
        echo "- {$role}: " . $izin->pluck('name')->implode(', ') . "\n";
    }
    echo "(Owner selalu bisa semua. Role buatan sendiri tidak dicentang otomatis — atur di Role & Hak Akses.)\n\n";
    $gudang = DB::table('cabangs')->where('tipe', 'gudang_pusat')->where('is_active', true)->get(['nama_cabang', 'kirim_cabang_aktif']);
    echo 'Gudang Pusat aktif: ' . ($gudang->isEmpty() ? '(belum ada — Produksi & Kirim ke Cabang butuh cabang tipe Gudang Pusat)'
        : $gudang->map(fn ($g) => $g->nama_cabang . ' (Kirim ke Cabang di POS: ' . ($g->kirim_cabang_aktif ? 'NYALA' : 'mati') . ')')->implode(', ')) . "\n";
    echo "Kirim ke Cabang bawaannya MATI — nyalakan di Cabang → Edit (Gudang Pusat) → Config POS kalau mau dipakai.\n\n";
} catch (\Throwable $e) {
    echo 'Tidak bisa membaca centangan: ' . $e->getMessage() . "\n\n";
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
    echo "Menu baru di Stok & Gudang: Produksi, Kiriman Cabang, Retur Cabang. Label Produk tetap mati per cabang sampai dinyalakan.\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-449.php DARI SERVER SEKARANG ⚠️\n";
