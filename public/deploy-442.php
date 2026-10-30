<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.42 (Ambil Produk Jual dari cabang lain + centang "Buat juga di
 * cabang") di hosting tanpa terminal. Tidak ada migration (tanpa perubahan
 * struktur database):
 *  1. Perbaikan438PanduanSeeder → panduan + tooltip baru (idempotent)
 *  2. clear & rebuild cache (route baru)
 *
 * Pengaman: wajib ?confirm=YES-AMBIL442 dan storage/deploy-442.lock belum ada.
 * URL: https://erp.dmentaiindonesia.com/deploy-442.php?confirm=YES-AMBIL442
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

if (! isset($_GET['confirm']) || ! hash_equals('YES-AMBIL442', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-AMBIL442 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-442.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.42 — Ambil Produk dari Cabang Lain ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['db:seed', ['--class' => 'Perbaikan438PanduanSeeder', '--force' => true]],
    ['view:clear', []], ['cache:clear', []], ['route:clear', []], ['config:cache', []], ['route:cache', []],
] as [$perintah, $opsi]) {
    echo '>>> php artisan ' . $perintah . ' ' . json_encode($opsi) . "\n";
    try {
        Artisan::call($perintah, $opsi);
        echo Artisan::output() . "\n";
    } catch (\Throwable $e) {
        $adaError = true;
        echo 'GAGAL: ' . $e->getMessage() . "\n\n";
        if ($perintah === 'db:seed') {
            echo "Berhenti — langkah berikutnya tidak dijalankan.\n";
            break;
        }
    }
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-442.php DARI SERVER SEKARANG ⚠️\n";
