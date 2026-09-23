<?php
/**
 * ⚠️⚠️ SCRIPT SEKALI PAKAI — MENGHAPUS SELURUH DATABASE ⚠️⚠️
 * HAPUS FILE INI DARI SERVER SEGERA SETELAH DIJALANKAN.
 *
 * Untuk hosting tanpa terminal/SSH: menjalankan `migrate:fresh --force` lalu
 * `db:seed --class=GoLiveSeeder --force` lewat browser.
 *
 * Pengaman berlapis (semua harus terpenuhi):
 *  1. APP_ENV=production
 *  2. .env berisi GOLIVE_ENABLED=true (hapus baris ini setelah selesai)
 *  3. GOLIVE_OWNER_EMAIL / GOLIVE_OWNER_PASSWORD sudah diganti dari default
 *  4. URL diakses dengan ?confirm=YES-GO-LIVE
 *  5. storage/golive.lock belum ada (mencegah wipe kedua secara tidak sengaja;
 *     hapus lock manual lewat File Manager kalau memang sengaja mengulang)
 *
 * URL: https://erp.dmentaiindonesia.com/golive.php?confirm=YES-GO-LIVE
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

function tolak(string $pesan, int $kode = 403): void
{
    http_response_code($kode);
    echo "DITOLAK: {$pesan}\n";
    exit;
}

// Config bisa ter-cache tanpa file golive.php — bersihkan dulu supaya .env terbaca.
Artisan::call('config:clear');

if (! app()->environment('production')) {
    tolak('APP_ENV bukan production (script ini hanya untuk production).');
}
if (! filter_var(config('golive.enabled'), FILTER_VALIDATE_BOOLEAN)) {
    tolak('GOLIVE_ENABLED=true belum diset di .env.');
}
if (config('golive.owner_email') === config('golive.default_email')
    || config('golive.owner_password') === config('golive.default_password')) {
    tolak('GOLIVE_OWNER_EMAIL / GOLIVE_OWNER_PASSWORD masih default. Isi kredensial real di .env.');
}
if (! isset($_GET['confirm']) || ! hash_equals('YES-GO-LIVE', (string) $_GET['confirm'])) {
    tolak('Tambahkan ?confirm=YES-GO-LIVE di URL. PERINGATAN: SELURUH DATA DATABASE AKAN DIHAPUS.');
}
$lockFile = storage_path('golive.lock');
if (file_exists($lockFile)) {
    tolak("Go-live sudah pernah dijalankan ({$lockFile} ada). Hapus file lock kalau memang sengaja mengulang.");
}

echo "=== GO-LIVE D'mentai ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

try {
    echo ">>> migrate:fresh --force\n";
    Artisan::call('migrate:fresh', ['--force' => true]);
    echo Artisan::output() . "\n";

    echo ">>> db:seed --class=GoLiveSeeder --force\n";
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\GoLiveSeeder', '--force' => true]);
    echo Artisan::output() . "\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'GAGAL: ' . $e->getMessage() . "\n";
    echo "Database bisa dalam keadaan setengah jadi. Perbaiki penyebabnya lalu jalankan ulang (lock belum dibuat).\n";
    exit;
}

file_put_contents($lockFile, date('c'));

echo "=== GO-LIVE BERHASIL ===\n\n";
echo 'Login Owner : ' . config('golive.owner_email') . "\n";
echo "Password    : sesuai GOLIVE_OWNER_PASSWORD di .env (tidak ditampilkan di layar)\n\n";
echo "LANGKAH WAJIB SEKARANG:\n";
echo "1. HAPUS public/golive.php dari server.\n";
echo "2. Hapus baris GOLIVE_ENABLED, GOLIVE_OWNER_EMAIL, GOLIVE_OWNER_PASSWORD, GOLIVE_OWNER_NAME dari .env.\n";
echo "3. Jalankan public/clear-cache.php (lalu hapus juga).\n";
echo "4. Login, lalu langsung ganti password Owner.\n";
