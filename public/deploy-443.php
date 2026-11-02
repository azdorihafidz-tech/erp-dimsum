<?php
/**
 * ⚠️ SCRIPT SEKALI PAKAI — HAPUS FILE INI DARI SERVER SETELAH DIJALANKAN ⚠️
 *
 * Deploy 4.43 (Rapikan Role & Hak Akses) di hosting tanpa terminal:
 *  1. migrate → izin baru + centangan warisan untuk role yang dulu sudah bisa
 *     membuka menunya (dicatat di hak_akses_migrasi_logs, bisa di-rollback)
 *  2. Perbaikan438PanduanSeeder → panduan Role & Hak Akses + tooltip (idempotent)
 *  3. clear & rebuild cache
 * TIDAK menjalankan RolePermissionSeeder (centangan buatan Owner tidak disentuh).
 *
 * Pengaman: wajib ?confirm=YES-HAKAKSES443 dan storage/deploy-443.lock belum ada.
 * URL: https://erp.dmentaiindonesia.com/deploy-443.php?confirm=YES-HAKAKSES443
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! isset($_GET['confirm']) || ! hash_equals('YES-HAKAKSES443', (string) $_GET['confirm'])) {
    http_response_code(403);
    echo "DITOLAK: tambahkan ?confirm=YES-HAKAKSES443 di URL.\n";
    exit;
}
$lockFile = storage_path('deploy-443.lock');
if (file_exists($lockFile)) {
    http_response_code(403);
    echo "DITOLAK: deploy ini sudah pernah dijalankan ({$lockFile} ada).\n";
    exit;
}

echo "=== Deploy 4.43 — Rapikan Role & Hak Akses ===\n";
echo 'Database: ' . config('database.connections.' . config('database.default') . '.database') . "\n\n";

$adaError = false;
foreach ([
    ['config:clear', []],
    ['cache:clear', []],
    ['migrate', ['--force' => true]],
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
        if (in_array($perintah, ['migrate', 'db:seed'], true)) {
            echo "Berhenti — langkah berikutnya tidak dijalankan.\n";
            break;
        }
    }
}

if (Schema::hasTable('hak_akses_migrasi_logs')) {
    echo "=== Centangan yang ditambahkan otomatis (role yang dulu sudah bisa membuka menunya) ===\n";
    $rows = DB::table('hak_akses_migrasi_logs as l')->join('permissions as p', 'p.id', '=', 'l.permission_id')
        ->where('l.jenis', 'centang')->orderBy('l.role')->orderBy('p.display_name')->get(['l.role', 'p.display_name']);
    foreach ($rows->groupBy('role') as $role => $izin) {
        echo "- {$role}: " . $izin->pluck('display_name')->implode(', ') . "\n";
    }
    if ($rows->isEmpty()) {
        echo "(tidak ada)\n";
    }
    echo "\n";
}

if (! $adaError) {
    @file_put_contents($lockFile, now()->toDateTimeString());
    echo "=== DEPLOY SELESAI ===\n";
} else {
    echo "=== SELESAI DENGAN ERROR — cek pesan di atas (lock tidak dibuat) ===\n";
}

echo "\n⚠️ HAPUS FILE deploy-443.php DARI SERVER SEKARANG ⚠️\n";
