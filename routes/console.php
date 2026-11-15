<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate recurring transaction otomatis tengah malam (sebelum hari kerja dimulai)
Schedule::command('recurring:generate')->dailyAt('00:01');

// Backup otomatis setiap hari jam 02:00 WIB
Schedule::command('backup:run --only-db --disable-notifications')->dailyAt('02:00');
Schedule::command('backup:clean --disable-notifications')->dailyAt('02:30');

// Generate penyusutan aset otomatis tanggal 1 tiap bulan jam 01:00 WIB (idempotent)
Schedule::command('aset:generate-depresiasi')->monthlyOn(1, '01:00');

// Cek pencapaian program loyalty otomatis harian jam 03:00 WIB (idempotent)
Schedule::command('loyalty:cek-pencapaian')->dailyAt('03:00');

// Stok Opname — pengingat akhir bulan (cadangan; utamanya dipicu saat aplikasi dibuka).
// Schedule::call di dalam proses — tidak butuh proc_open/exec yang sering dimatikan hosting.
Schedule::call(fn () => app(\App\Services\StokOpnamePengingat::class)->jalankan())->hourly()->name('stok-opname-pengingat')->withoutOverlapping();
