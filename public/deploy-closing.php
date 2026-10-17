<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy fitur Closing / Tutup Buku (2026-10-17) di hosting tanpa terminal.
 * Semua langkah ADDITIVE — tidak menghapus/mengubah data yang sudah ada:
 *  1. migrate --force   → kolom pengaturan_umums.tanggal_mulai_periode (default 1)
 *                          + tabel baru closings & closing_kas
 *  2. ClosingPermissionSeeder → 6 permission closing.* + mapping role default
 *                          (insertOrIgnore, kustomisasi Role & Hak Akses TIDAK disentuh)
 *  3. ClosingPanduanSeeder    → panduan "closing" + tooltip (updateOrCreate)
 *  4. clear & rebuild cache
 *
 * Pengaman: wajib ?confirm=YES-CLOSING dan storage/deploy-closing.lock belum ada.
 * Disarankan backup database dulu lewat cPanel (phpMyAdmin → Export).
 *
 * URL: https://erp.dmentaiindonesia.com/deploy-closing.php?confirm=YES-CLOSING
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

if (! isset($_GET['confirm']) || ! hash_equals('YES-CLOSING', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-CLOSING di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-closing.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy closing sudah pernah dijalankan ({$lockFile} ada).\n";
    echo "Hapus file lock lewat File Manager kalau memang sengaja mengulang.\n";
    exit;
}

$langkah = [
    ['config:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'ClosingPermissionSeeder', '--force' => true]],
    ['db:seed', ['--class' => 'ClosingPanduanSeeder', '--force' => true]],
    ['view:clear', []],
    ['cache:clear', []],
    ['route:clear', []],
    ['config:cache', []],
    ['route:cache', []],
];

echo "=== Deploy Closing / Tutup Buku ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ($langkah as [$perintah, $opsi]) {
    echo '>>> php artisan ' . $perintah . ' ' . json_encode($opsi) . "\n";
    try {
        Artisan::call($perintah, $opsi);
        echo Artisan::output() . "\n";
    } catch (\Throwable $e) {
        $adaError = true;
        echo 'GAGAL: ' . $e->getMessage() . "\n\n";
        if ($perintah === 'migrate' || $perintah === 'db:seed') {
            echo "Berhenti — langkah berikutnya tidak dijalankan.\n";
            break;
        }
    }
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY CLOSING SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat, bisa diulang setelah diperbaiki) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-closing.php DARI SERVER SEKARANG ⚠️\n";
