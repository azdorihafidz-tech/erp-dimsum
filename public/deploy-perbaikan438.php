<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH SELESAI ⚠️
 *
 * Deploy Perbaikan 4.38 (harga, wajib bukti, PO ke Gudang, role bebas) +
 * 4.39 (data barang terpisah per cabang) di hosting tanpa terminal.
 *
 * DUA TAHAP (wajib berurutan):
 *  1. SIMULASI — ?confirm=YES-PERBAIKAN&tahap=simulasi
 *     - migrate --force (semua additive: kolom baru + cabang_id pemilik barang)
 *     - tampilkan RENCANA pemecahan barang per cabang. Data barang TIDAK diubah.
 *     Boleh diulang berkali-kali.
 *  2. JALANKAN — ?confirm=YES-PERBAIKAN&tahap=jalankan
 *     - pecah data barang/resep/Bumbu Pusat ke cabang pemakainya (bisa
 *       dibatalkan: php artisan erp:pecah-cabang --batalkan, atau rollback migration)
 *     - Perbaikan438PanduanSeeder (panduan + tooltip, idempotent)
 *     - clear & rebuild cache, lalu buat lock storage/deploy-perbaikan438.lock
 *
 * WAJIB backup database dulu lewat cPanel (phpMyAdmin → Export) sebelum tahap 2.
 *
 * URL: https://erp.dmentaiindonesia.com/deploy-perbaikan438.php?confirm=YES-PERBAIKAN&tahap=simulasi
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
    echo "DITOLAK: tambahkan ?confirm=YES-PERBAIKAN&tahap=simulasi di URL.\n";
    exit;
}
$tahap = (string) ($_GET['tahap'] ?? '');
if (! in_array($tahap, ['simulasi', 'jalankan'], true)) {
    http_response_code(400);
    echo "Pilih tahap: &tahap=simulasi (cek rencana dulu) atau &tahap=jalankan.\n";
    exit;
}
$lockFile = storage_path('deploy-perbaikan438.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy perbaikan sudah pernah dijalankan ({$lockFile} ada).\n";
    echo "Hapus file lock lewat File Manager kalau memang sengaja mengulang.\n";
    exit;
}

function jalankanArtisan(string $perintah, array $opsi = []): bool
{
    echo '>>> php artisan ' . $perintah . ' ' . json_encode($opsi) . "\n";
    try {
        Artisan::call($perintah, $opsi);
        echo Artisan::output() . "\n";
        return true;
    } catch (\Throwable $e) {
        echo 'GAGAL: ' . $e->getMessage() . "\n\n";
        return false;
    }
}

echo "=== Deploy Perbaikan 4.38 + 4.39 — tahap: " . strtoupper($tahap) . " ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

jalankanArtisan('config:clear');
if (! jalankanArtisan('migrate', ['--force' => true])) {
    echo "Berhenti — perbaiki error migrate dulu.\n";
    exit;
}

$pecah = app(\App\Services\PemecahanDataCabangService::class);

if ($tahap === 'simulasi') {
    echo "=== RENCANA PEMECAHAN DATA PER CABANG (belum dijalankan) ===\n";
    echo $pecah->perluDipecah() ? $pecah->laporanRencana() . "\n" : "Semua barang sudah punya cabang.\n";
    echo "\nPeriksa daftar di atas. Kalau sudah sesuai: BACKUP DATABASE, lalu buka\n";
    echo "?confirm=YES-PERBAIKAN&tahap=jalankan\n";
    exit;
}

$adaError = false;
try {
    if ($pecah->perluDipecah()) {
        $hasil = $pecah->jalankan();
        echo "Pemecahan selesai. Barang: {$hasil['barang']}, Bumbu Pusat: {$hasil['bumbu']}, salinan baru: {$hasil['salinan']}, baris riwayat dipindah: {$hasil['baris_dipindah']}.\n\n";
    } else {
        echo "Semua barang sudah punya cabang — pemecahan dilewati.\n\n";
    }
} catch (\Throwable $e) {
    echo 'GAGAL memecah data: ' . $e->getMessage() . "\n(Data tidak berubah — proses dibungkus transaksi.)\n";
    exit;
}

foreach ([
    ['db:seed', ['--class' => 'Perbaikan438PanduanSeeder', '--force' => true]],
    ['view:clear', []], ['cache:clear', []], ['route:clear', []], ['config:cache', []], ['route:cache', []],
] as [$perintah, $opsi]) {
    if (! jalankanArtisan($perintah, $opsi)) {
        $adaError = true;
    }
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-perbaikan438.php DARI SERVER SEKARANG ⚠️\n";
