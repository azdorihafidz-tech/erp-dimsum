<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI SEGERA SETELAH DIJALANKAN ⚠️
 *
 * Dipakai utk clear+rebuild cache Laravel di shared hosting yang TIDAK
 * PUNYA terminal/SSH (mis. Rumah Web cPanel) — jalankan config:clear,
 * view:clear, cache:clear, route:clear, lalu rebuild optimize (config+route
 * cache) via HTTP, cukup akses URL-nya di browser.
 *
 * CARA PAKAI:
 * 1. Upload/deploy file ini ke public/ (sudah otomatis ikut kalau git pull)
 * 2. Buka https://domain-anda.com/clear-cache.php di browser
 * 3. Cek output "SEMUA CACHE BERHASIL DI-CLEAR & DI-REBUILD"
 * 4. LANGSUNG HAPUS FILE INI dari server (lewat File Manager cPanel atau
 *    git rm + push) — file ini TIDAK ADA proteksi auth, siapapun yang tahu
 *    URL-nya bisa clear cache aplikasi Anda kalau dibiarkan online.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$commands = ['config:clear', 'view:clear', 'cache:clear', 'route:clear', 'config:cache', 'route:cache'];

echo "=== Clear & Rebuild Cache Laravel ===\n\n";

$adaError = false;
foreach ($commands as $command) {
    echo ">>> php artisan {$command}\n";
    try {
        \Illuminate\Support\Facades\Artisan::call($command);
        echo \Illuminate\Support\Facades\Artisan::output();
    } catch (\Throwable $e) {
        $adaError = true;
        echo "GAGAL: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo $adaError
    ? "=== SELESAI DENGAN ERROR — cek pesan di atas ===\n"
    : "=== SEMUA CACHE BERHASIL DI-CLEAR & DI-REBUILD ===\n";

echo "\n⚠️ JANGAN LUPA HAPUS FILE clear-cache.php INI DARI SERVER SEKARANG ⚠️\n";
