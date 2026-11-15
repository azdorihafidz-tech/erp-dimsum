<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.51 — Penggajian:
 * - menit_telat_total + potongan_telat: "Potongan telat per menit" (Pengaturan Penggajian) dulu
 *   tersimpan tapi tidak pernah dipakai menghitung gaji.
 * - kas_id + transaksi_keuangan_id: tombol Bayar sekarang mengeluarkan uang dari kas yang dipilih
 *   (dulu hanya mengubah status — gaji harus dicatat manual di Kas & Transaksi).
 * Semua nullable/0 → slip lama tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->unsignedInteger('menit_telat_total')->default(0)->after('jumlah_alpha');
            $table->decimal('potongan_telat', 15, 2)->default(0)->after('potongan_absensi');
            $table->foreignId('kas_id')->nullable()->after('tanggal_bayar')->constrained('kas')->nullOnDelete();
            $table->unsignedBigInteger('transaksi_keuangan_id')->nullable()->after('kas_id');
        });
    }

    public function down(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropForeign(['kas_id']);
            $table->dropColumn(['menit_telat_total', 'potongan_telat', 'kas_id', 'transaksi_keuangan_id']);
        });
    }
};
