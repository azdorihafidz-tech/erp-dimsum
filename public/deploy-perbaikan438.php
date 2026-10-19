<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy Perbaikan Harga & fitur lanjutan (CLAUDE.md 4.38, 2026-10-18) di
 * hosting tanpa terminal. Semua langkah ADDITIVE:
 *  1. migrate --force
 *     - items.harga_po_terakhir(+_at), item_cabang.harga_beli,
 *       stock_transfer_items.harga_per_unit           (Tahap A)
 *     - cabangs.wajib_bukti_transfer / wajib_bukti_qris (default aktif) (Tahap B)
 *     - suppliers.cabang_id, orders.purchase_order_id (Tahap C)
 *     - tabel custom_roles + users.role ENUM → VARCHAR (Tahap D)
 *     (Tahap E/F — harga & stok per outlet — tanpa migration baru: memakai
 *      item_cabang.harga_beli/harga_override & stocks.qty_minimum yang sudah ada)
 *  2. Perbaikan438PanduanSeeder → panduan + tooltip Tahap A–F (idempotent)
 *  3. clear & rebuild cache
 *
 * Pengaman: wajib ?confirm=YES-PERBAIKAN dan storage/deploy-perbaikan438.lock belum ada.
 * Disarankan backup database dulu lewat cPanel (phpMyAdmin → Export).
 *
 * URL: https://erp.dmentaiindonesia.com/deploy-perbaikan438.php?confirm=YES-PERBAIKAN
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;

if (! isset($_GET['confirm']) || ! hash_equals('YES-PERBAIKAN', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-PERBAIKAN di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-perbaikan438.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy perbaikan sudah pernah dijalankan ({$lockFile} ada).\n";
    echo "Hapus file lock lewat File Manager kalau memang sengaja mengulang.\n";
    exit;
}

$langkah = [
    ['config:clear', []],
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'Perbaikan438PanduanSeeder', '--force' => true]],
    ['view:clear', []],
    ['cache:clear', []],
    ['route:clear', []],
    ['config:cache', []],
    ['route:cache', []],
];

echo "=== Deploy Perbaikan Harga & Fitur (4.38) ===\n";
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
    echo "=== DEPLOY PERBAIKAN SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat, bisa diulang setelah diperbaiki) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-perbaikan438.php DARI SERVER SEKARANG ⚠️\n";
