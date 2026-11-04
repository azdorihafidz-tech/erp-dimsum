<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.45 (Batalkan vs Hapus order + laporan tidak menghitung order
 * terhapus / bill belum dibayar) di hosting tanpa terminal. Jalankan sekali di
 * SETIAP instalasi (D'mentai live & demo). Tidak ada migration — perubahan
 * laporan & tombol sudah berlaku begitu kode di-pull; script ini hanya:
 *  1. Perbaikan438PanduanSeeder → bagian "Batalkan vs Hapus" di panduan Riwayat Order (idempotent)
 *  2. clear & rebuild cache
 * Juga mencetak daftar order SELESAI yang terlanjur dihapus (untuk dipulihkan
 * manual lewat Data Terhapus lalu dibatalkan). Tidak ada data order yang diubah.
 *
 * Pengaman: wajib ?confirm=YES-BATAL445 dan storage/deploy-445.lock belum ada.
 * URL: https://<alamat-erp>/deploy-445.php?confirm=YES-BATAL445
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! isset($_GET['confirm']) || ! hash_equals('YES-BATAL445', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-BATAL445 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-445.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.45 — Batalkan vs Hapus order ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
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

echo "=== Order SELESAI yang terlanjur dihapus (stok & kas belum dikembalikan) ===\n";
echo "Pulihkan di Data Terhapus → Orders, lalu buka Detail Order → Batalkan.\n";
$rows = DB::table('orders as o')->join('cabangs as c', 'c.id', '=', 'o.cabang_id')
    ->whereNotNull('o.deleted_at')->whereIn('o.status', ['selesai', 'proses'])
    ->orderBy('c.nama_cabang')->orderBy('o.tanggal_order')
    ->get(['c.nama_cabang', 'o.nomor_order', 'o.tanggal_order', 'o.total_bayar', 'o.deleted_at']);
foreach ($rows as $r) {
    echo sprintf("- %-22s %-18s %s  Rp %12s  (dihapus %s)\n", $r->nama_cabang, $r->nomor_order,
        substr((string) $r->tanggal_order, 0, 10), number_format((float) $r->total_bayar, 0, ',', '.'), $r->deleted_at);
}
echo 'Jumlah: ' . $rows->count() . ' order, total Rp ' . number_format((float) $rows->sum('total_bayar'), 0, ',', '.') . "\n\n";

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-445.php DARI SERVER SEKARANG ⚠️\n";
