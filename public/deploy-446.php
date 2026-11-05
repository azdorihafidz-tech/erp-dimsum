<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.46 (Laporan Transaksi / Produk / Kategori di menu Penjualan, bisa
 * dibuka kasir) di hosting tanpa terminal. Jalankan sekali di SETIAP instalasi
 * (D'mentai live & demo). Tidak ada migration. Script ini hanya:
 *  1. LaporanPenjualanKasirSeeder → 4 izin baru + centangan bawaan (ADDITIVE,
 *     insertOrIgnore — centangan yang sudah diatur Owner tidak diubah) +
 *     panduan + tooltip (idempotent)
 *  2. clear & rebuild cache
 * Lalu mencetak role yang mendapat izin. Tidak ada data transaksi yang diubah.
 *
 * Pengaman: wajib ?confirm=YES-LAPORAN446 dan storage/deploy-446.lock belum ada.
 * URL: https://<alamat-erp>/deploy-446.php?confirm=YES-LAPORAN446
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-LAPORAN446', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-LAPORAN446 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-446.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.46 — Laporan di menu Penjualan ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['db:seed', ['--class' => 'LaporanPenjualanKasirSeeder', '--force' => true]],
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

echo "=== Role yang mendapat izin (Owner selalu bisa semua) ===\n";
$rows = DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
    ->where('p.name', 'like', 'penjualan.laporan%')
    ->orderBy('p.name')->orderBy('rp.role')
    ->get(['p.display_name', 'rp.role']);
foreach ($rows->groupBy('display_name') as $izin => $r) {
    echo "- {$izin}: " . $r->pluck('role')->implode(', ') . "\n";
}
echo "\nUntuk role lain (mis. Team), centang manual di menu Role & Hak Akses.\n\n";

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-446.php DARI SERVER SEKARANG ⚠️\n";
