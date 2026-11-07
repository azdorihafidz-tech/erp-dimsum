<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.47 (POS: kolom Harga/Unit terkunci kecuali punya izin, produk sama
 * digabung jadi qty, panel bayar mode Tablet) di hosting tanpa terminal.
 * Jalankan sekali di SETIAP instalasi (D'mentai live & demo). Tidak ada
 * migration. Script ini hanya:
 *  1. PosUbahHargaSeeder → izin baru "Ubah Harga Jual di POS" (TIDAK dicentang
 *     untuk role mana pun — keputusan Owner) + panduan POS + tooltip (idempotent)
 *  2. clear & rebuild cache
 * Lalu mencetak role yang memegang izin itu. Tidak ada data transaksi yang diubah.
 * ⚠️ Setelah ini kasir TIDAK BISA lagi mengubah harga di POS sampai Owner
 * mencentang izinnya di Role & Hak Akses.
 *
 * Pengaman: wajib ?confirm=YES-POS447 dan storage/deploy-447.lock belum ada.
 * URL: https://<alamat-erp>/deploy-447.php?confirm=YES-POS447
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-POS447', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-POS447 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-447.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.47 — POS: harga terkunci, produk sama digabung, panel bayar tablet ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['db:seed', ['--class' => 'PosUbahHargaSeeder', '--force' => true]],
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

echo "=== Role yang memegang izin Ubah Harga Jual di POS (Owner selalu bisa) ===\n";
$rows = DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
    ->where('p.name', 'order.ubah_harga')->orderBy('rp.role')->pluck('rp.role');
echo $rows->isEmpty() ? "- (belum ada — hanya Owner)\n" : '- ' . $rows->implode(', ') . "\n";
echo "\nKalau ada role yang boleh mengubah harga, centang di Role & Hak Akses → Penjualan.\n\n";

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-447.php DARI SERVER SEKARANG ⚠️\n";
