<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok Opname Tahap C — riwayat tindak lanjut stok rusak: kembali ke pemasok
 * (diganti barang / dibayar uang / dipotong tagihan), dimusnahkan (berita acara),
 * dipakai lagi. Hanya tabel baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_rusak_tindakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_rusak_id')->constrained('stok_rusak')->cascadeOnDelete();
            $table->string('jenis', 20); // ganti_barang | bayar_uang | potong_tagihan | musnahkan | pakai_lagi
            $table->decimal('qty', 15, 3);
            $table->decimal('nilai', 15, 2)->default(0);  // harga pokok × qty
            $table->decimal('uang', 15, 2)->default(0);   // uang yang diterima (pemasok)
            $table->foreignId('kas_id')->nullable()->constrained('kas')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('nomor_po', 60)->nullable();
            $table->string('nomor_ba', 30)->nullable()->unique();
            $table->string('foto_path', 255)->nullable();
            $table->string('saksi', 150)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->date('tanggal');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_rusak_tindakan');
    }
};
