<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CabangSeeder::class,
            PermissionSeeder::class,        // Harus sebelum UserSeeder & RolePermissionSeeder
            JenisOlahanSeeder::class,
            KategoriTransaksiSeeder::class, // Buat kategori keuangan dinamis
            KategoriPengeluaranBackfillSeeder::class, // Backfill kategori_pengeluaran dari data lama (idempotent)
            UserSeeder::class,
            EvaluationAspectSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            ResepBumbuSeeder::class,        // Seed 3 resep bumbu starting point (idempotent, butuh ItemSeeder & JenisOlahanSeeder)
            AssetCategorySeeder::class,
            PembelianPenjualanSeeder::class,
            BackfillStockQtyMinimumSeeder::class, // Backfill stocks.qty_minimum dari items.qty_minimum (idempotent)
            HRSeeder::class,
            RolePermissionSeeder::class,    // Harus paling akhir (butuh semua permission sudah ada)
            TooltipAdjustmentSeeder::class, // Tooltip percontohan modul adjustment
            PanduanPosSeeder::class,        // Panduan percontohan modul POS
            PanduanStubSeeder::class,       // Panduan stub 46 menu + update modul POS → penjualan
            PanduanKontenSeeder::class,     // Isi konten nyata 46 panduan dari stub
            TooltipKontenSeeder::class,     // Tooltip field-field bermakna seluruh aplikasi
        ]);
    }
}
