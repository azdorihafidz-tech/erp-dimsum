<?php

use App\Support\StokOpnameAkun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stok Opname Tahap A (RANCANGAN_STOK_OPNAME.md): opname, penghitung, barang
 * yang dihitung, rincian penyebab, stok rusak (penampung) + pengaturan di
 * pengaturan_umums + akun COA & kategori transaksi baru.
 * Hanya tabel/kolom baru — data lama tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->string('cakupan', 20)->default('semua'); // semua | kategori | barang
            $table->json('cakupan_ids')->nullable();
            $table->boolean('hitung_buta')->default(true);
            $table->string('status', 20)->default('menghitung'); // menghitung | diajukan | disetujui | dibatalkan
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diajukan_at')->nullable();
            $table->foreignId('diajukan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditolak_at')->nullable();
            $table->foreignId('ditolak_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->foreignId('disetujui_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_disetujui')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->foreignId('dibatalkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_batal')->nullable();
            $table->json('ringkasan')->nullable();
            $table->decimal('akurasi', 5, 2)->nullable();
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('stok_opname_penghitung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opnames')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['stok_opname_id', 'user_id']);
        });

        Schema::create('stok_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_id')->constrained('stok_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_sistem', 15, 3)->nullable();   // stok sistem SAAT barang dihitung
            $table->decimal('qty_fisik', 15, 3)->nullable();
            $table->timestamp('dihitung_at')->nullable();
            $table->foreignId('dihitung_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('hitung_ulang')->default(false);
            $table->boolean('dikeluarkan')->default(false);
            $table->string('alasan_dikeluarkan', 255)->nullable();
            $table->decimal('harga_pokok', 15, 4)->nullable();  // per satuan, saat disetujui
            $table->decimal('nilai_selisih', 15, 2)->nullable(); // minus = kurang
            $table->timestamps();
            $table->unique(['stok_opname_id', 'item_id']);
        });

        Schema::create('stok_opname_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_opname_item_id')->constrained('stok_opname_items')->cascadeOnDelete();
            $table->string('penyebab', 20);
            $table->decimal('qty', 15, 3);
            $table->string('foto_path', 255)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->decimal('nilai', 15, 2)->nullable();
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();
        });

        Schema::create('stok_rusak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('stok_opname_rincian_id')->nullable()->constrained('stok_opname_rincian')->nullOnDelete();
            $table->string('penyebab', 20);
            $table->decimal('qty_awal', 15, 3);
            $table->decimal('qty_sisa', 15, 3);
            $table->decimal('harga_pokok', 15, 4);
            $table->date('tanggal');
            $table->string('status', 20)->default('tertahan'); // tertahan | selesai
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->boolean('opname_hitung_buta')->default(true);
            $table->decimal('opname_batas_foto_hilang', 15, 2)->default(200000);
            $table->boolean('opname_pengingat_aktif')->default(true);
            $table->unsignedTinyInteger('opname_pengingat_hari')->default(3);
            $table->string('opname_pengingat_jam', 5)->default('08:00');
        });

        StokOpnameAkun::pastikan();
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn(['opname_hitung_buta', 'opname_batas_foto_hilang', 'opname_pengingat_aktif', 'opname_pengingat_hari', 'opname_pengingat_jam']);
        });
        Schema::dropIfExists('stok_rusak');
        Schema::dropIfExists('stok_opname_rincian');
        Schema::dropIfExists('stok_opname_items');
        Schema::dropIfExists('stok_opname_penghitung');
        Schema::dropIfExists('stok_opnames');

        // Akun & kategori hanya dihapus kalau belum pernah dipakai transaksi
        $dipakai = DB::table('transaksi_keuangans')->whereIn('kategori_id',
            DB::table('kategori_transaksis')->whereIn('kode', array_keys(StokOpnameAkun::KATEGORI))->pluck('id'))->exists();
        if (! $dipakai) {
            DB::table('kategori_transaksis')->whereIn('kode', array_keys(StokOpnameAkun::KATEGORI))->delete();
            DB::table('chart_of_accounts')->whereIn('kode', array_keys(StokOpnameAkun::AKUN))->delete();
        }
    }
};
