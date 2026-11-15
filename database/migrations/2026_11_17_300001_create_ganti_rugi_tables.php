<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok Opname Tahap D — ganti rugi karyawan untuk barang Hilang:
 * ganti_rugi (siapa, berapa, tunai/potong gaji, cicilan 1–12),
 * ganti_rugi_potongan (potongan per slip gaji; dibuat ulang saat slip draft
 * dihitung ulang → tidak dobel), penggajians.potongan_ganti_rugi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ganti_rugi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opnames');
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('karyawan_id')->constrained('karyawans');
            $table->decimal('nilai', 15, 2);
            $table->string('alasan_nilai', 255)->nullable();
            $table->string('cara', 20); // tunai | potong_gaji
            $table->unsignedTinyInteger('jumlah_cicilan')->default(1);
            $table->char('mulai_periode', 7)->nullable(); // YYYY-MM, potong gaji
            $table->decimal('terbayar', 15, 2)->default(0);
            $table->string('status', 20)->default('berjalan'); // berjalan | lunas | batal
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('dibuat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alasan_batal', 255)->nullable();
            $table->timestamps();
            $table->index(['karyawan_id', 'status']);
        });

        Schema::create('ganti_rugi_potongan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ganti_rugi_id')->constrained('ganti_rugi')->cascadeOnDelete();
            $table->foreignId('penggajian_id')->constrained('penggajians')->cascadeOnDelete();
            $table->char('periode', 7);
            $table->decimal('nominal', 15, 2);
            $table->string('status', 20)->default('di_slip'); // di_slip | dibayar
            $table->foreignId('tk_gaji_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('tk_pulih_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ganti_rugi_id', 'penggajian_id']);
        });

        Schema::table('penggajians', function (Blueprint $table) {
            $table->decimal('potongan_ganti_rugi', 15, 2)->default(0)->after('potongan_lain');
        });
    }

    public function down(): void
    {
        Schema::table('penggajians', function (Blueprint $table) {
            $table->dropColumn('potongan_ganti_rugi');
        });
        Schema::dropIfExists('ganti_rugi_potongan');
        Schema::dropIfExists('ganti_rugi');
    }
};
