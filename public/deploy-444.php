<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.44 (porting erp-coffeeshop: POS pilih cabang, Identitas Usaha,
 * APK Kasir, Label Produk) di hosting tanpa terminal. Jalankan sekali di
 * SETIAP instalasi (D'mentai live & demo) — masing-masing memakai .env/DB-nya sendiri.
 *  1. migrate → kolom identitas usaha (diisi "D'mentai" · "Dimsum & Gyoza" kalau
 *     masih kosong, tampilan tidak berubah) + kolom Label Produk di cabang (bawaan MATI)
 *  2. IdentitasUsahaSeeder → panduan Pengaturan Umum + bagian Aplikasi Kasir di panduan POS
 *  3. LabelProdukSeeder → izin Cetak Label Produk untuk role yang sudah bisa transaksi POS
 *     (ADDITIVE, centangan buatan Owner tidak disentuh) + panduan + tooltip
 *  4. clear & rebuild cache
 * TIDAK menjalankan RolePermissionSeeder.
 *
 * Pengaman: wajib ?confirm=YES-PORTING444 dan storage/deploy-444.lock belum ada.
 * URL: https://<alamat-erp>/deploy-444.php?confirm=YES-PORTING444
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! isset($_GET['confirm']) || ! hash_equals('YES-PORTING444', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-PORTING444 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-444.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.44 — Porting dari erp-coffeeshop ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'IdentitasUsahaSeeder', '--force' => true]],
    ['db:seed', ['--class' => 'LabelProdukSeeder', '--force' => true]],
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

if (Schema::hasColumn('pengaturan_umums', 'nama_singkat')) {
    $p = DB::table('pengaturan_umums')->first();
    echo "=== Identitas usaha ===\n";
    echo '- Nama usaha  : ' . ($p->nama_perusahaan ?? '-') . "\n";
    echo '- Nama singkat: ' . ($p->nama_singkat ?? '-') . "\n";
    echo '- Tagline     : ' . ($p->tagline ?? '-') . "\n";
    echo '- Logo upload : ' . (($p->logo_path ?? null) ?: '(tidak ada — pakai logo bawaan)') . "\n\n";
}

if (Schema::hasColumn('cabangs', 'label_cup_aktif')) {
    echo "=== Label Produk ===\n";
    echo '- Cabang dengan label aktif: ' . DB::table('cabangs')->where('label_cup_aktif', true)->count() . " (bawaan mati)\n";
    $roles = DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
        ->where('p.name', 'label_cup.print')->orderBy('rp.role')->pluck('rp.role');
    echo '- Izin Cetak Label Produk: ' . ($roles->isEmpty() ? '(tidak ada)' : $roles->implode(', ')) . "\n\n";
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-444.php DARI SERVER SEKARANG ⚠️\n";
