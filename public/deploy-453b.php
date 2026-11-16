<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * HANYA untuk instalasi yang SUDAH menjalankan deploy-452.php sebelum perubahan
 * "Aktivitas Tim IT khusus Tim IT" (2026-11-16). Memperbarui teks Kamus Menu &
 * Panduan (seeder idempotent) + membersihkan cache. Tidak ada migration.
 * Kalau deploy-452.php BELUM pernah dijalankan: tidak perlu file ini — cukup
 * jalankan deploy-452.php (sudah memuat perubahan ini), lalu hapus kedua file.
 *
 * URL: https://<alamat-erp>/deploy-453b.php?confirm=YES-TIMIT453B
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

if (! isset($_GET['confirm']) || ! hash_equals('YES-TIMIT453B', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-TIMIT453B di URL.\n";
    exit;
}
$lock = storage_path('deploy-453b.lock');
if (file_exists($lock)) {
    http_response_code(403);
    echo "DITOLAK: sudah pernah dijalankan ({$lock} ada).\n";
    exit;
}
if (! \Illuminate\Support\Facades\Schema::hasTable('lisensi')) {
    http_response_code(409);
    echo "DITOLAK: deploy-452.php belum dijalankan di instalasi ini. Jalankan deploy-452.php saja (sudah termasuk perubahan ini), lalu hapus file ini.\n";
    exit;
}

$error = false;
foreach ([
    ['config:clear', []],
    ['db:seed', ['--class' => 'PaketLanggananSeeder', '--force' => true]],
    ['db:seed', ['--class' => 'KamusMenuSeeder', '--force' => true]],
    ['view:clear', []], ['cache:clear', []], ['route:clear', []], ['config:cache', []], ['route:cache', []],
] as [$cmd, $opt]) {
    echo ">>> php artisan {$cmd}\n";
    try {
        Artisan::call($cmd, $opt);
        echo Artisan::output() . "\n";
    } catch (\Throwable $e) {
        $error = true;
        echo 'GAGAL: ' . $e->getMessage() . "\n";
        break;
    }
}

if (! $error) {
    @file_put_contents($lock, now()->toDateTimeString());
    echo "=== SELESAI — Aktivitas Tim IT sekarang khusus Tim IT ===\n";
}
echo "\n⚠️ HAPUS FILE deploy-453b.php DARI SERVER SEKARANG ⚠️\n";
