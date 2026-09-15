<?php

namespace Database\Seeders;

use App\Models\LoyaltyProgram;
use Illuminate\Database\Seeder;

/**
 * Program loyalty pertama: "10 pelanggan yang mencapai transaksi 500 kg
 * giling → dapat hadiah". Periode all-time (periode_mulai/akhir NULL).
 * Idempotent — dicek by nama, tidak menimpa kalau Owner sudah edit manual
 * via UI (mis. ganti target/hadiah).
 */
class ProgramLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        $nama = 'Hadiah Loyalty 500 Kg';

        if (LoyaltyProgram::withTrashed()->where('nama', $nama)->exists()) {
            $this->command?->info("  [Loyalty] Program \"{$nama}\" sudah ada — tidak ada aksi (idempotent).");
            return;
        }

        LoyaltyProgram::create([
            'nama'          => $nama,
            'deskripsi'     => 'Pelanggan yang mencapai kumulatif 500 kg jasa giling (all-time) mendapatkan hadiah.',
            'target_qty_kg' => 500,
            'satuan_qty'    => 'kg',
            'sumber_data'   => 'orders.berat_daging_kg',
            'tipe_item'     => 'jasa_giling',
            'periode_mulai' => null,
            'periode_akhir' => null,
            'hadiah'        => 'Beras 5kg, Minyak Goreng 2L',
            'berulang'      => false,
            'status'        => 'aktif',
        ]);

        $this->command?->info("  [Loyalty] Program \"{$nama}\" berhasil dibuat.");
    }
}
