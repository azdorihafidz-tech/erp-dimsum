<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.48 (Warna Aplikasi di Pengaturan Umum) di hosting tanpa terminal.
 * Jalankan sekali di SETIAP instalasi (D'mentai live & demo). Script ini:
 *  1. migrate → 3 kolom baru pengaturan_umums.warna_utama/_sidebar/_latar
 *     (kosong = warna bawaan, jadi tampilan TIDAK berubah setelah deploy)
 *  2. WarnaAplikasiSeeder → panduan + tooltip (idempotent, tidak menyentuh
 *     izin/role)
 *  3. clear & rebuild cache
 * Tidak ada data transaksi yang diubah.
 *
 * Pengaman: wajib ?confirm=YES-WARNA448 dan storage/deploy-448.lock belum ada.
 * URL: https://<alamat-erp>/deploy-448.php?confirm=YES-WARNA448
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

if (! isset($_GET['confirm']) || ! hash_equals('YES-WARNA448', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-WARNA448 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-448.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.48 — Warna Aplikasi ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'WarnaAplikasiSeeder', '--force' => true]],
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

$b = app(\App\Support\Branding::class);
$b->lupakan();
echo "=== Warna aktif sekarang ===\n";
foreach (['utama' => 'Warna Utama', 'sidebar' => 'Warna Sidebar', 'latar' => 'Warna Latar'] as $jenis => $label) {
    echo "- {$label}: " . $b->warna($jenis) . "\n";
}
echo $b->warnaBawaan() ? "(warna bawaan — tampilan sama seperti sebelumnya)\n\n" : "\n";

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
    echo "Ganti warna: Pengaturan Umum → Warna Aplikasi (khusus Owner).\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-448.php DARI SERVER SEKARANG ⚠️\n";
