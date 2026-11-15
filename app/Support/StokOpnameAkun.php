<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun COA & kategori transaksi untuk Stok Opname (RANCANGAN_STOK_OPNAME.md
 * bagian 4). Idempotent — dipanggil dari migration, seeder, dan service
 * (pengaman kalau seeder belum jalan). Akun lama 6-1401 TIDAK diubah.
 *
 * Catatan: Laba Rugi Formal menjumlah nilai per akun tanpa melihat masuk/
 * keluar, jadi kelebihan & pemulihan memakai akun PENDAPATAN sendiri, dan
 * pembatalan kerugian memakai baris minus di akun beban yang sama.
 */
class StokOpnameAkun
{
    /** kode => [nama, tipe, saldo_normal, keterangan] */
    public const AKUN = [
        '6-1402' => ['Beban Kerugian Stok – Rusak', 'beban_operasional', 'debet', 'Stok Opname: rusak, pecah kemasan, freezer mati'],
        '6-1403' => ['Beban Kerugian Stok – Basi/Kedaluwarsa', 'beban_operasional', 'debet', 'Stok Opname: basi / kedaluwarsa'],
        '6-1404' => ['Beban Kerugian Stok – Hilang', 'beban_operasional', 'debet', 'Stok Opname: barang hilang'],
        '6-1405' => ['Beban Penyusutan Stok', 'beban_operasional', 'debet', 'Stok Opname: susut berat / thawing'],
        '6-1406' => ['Selisih Persediaan', 'beban_operasional', 'debet', 'Stok Opname & Penyesuaian Stok: salah catat'],
        '6-1501' => ['Beban Promosi & Sampel', 'beban_operasional', 'debet', 'Stok Opname: sampel / tester / promosi'],
        '7-1103' => ['Selisih Lebih Persediaan', 'pendapatan_lain', 'kredit', 'Stok Opname: fisik lebih banyak dari sistem'],
        '7-1104' => ['Pemulihan Kerugian Stok', 'pendapatan_lain', 'kredit', 'Ganti rugi karyawan / uang dari pemasok'],
    ];

    /** kode kategori => [nama, tipe, kode_akun_coa, urutan] */
    public const KATEGORI = [
        'OPN-RUSAK'    => ['Kerugian Stok – Rusak', 'pengeluaran', '6-1402', 60],
        'OPN-BASI'     => ['Kerugian Stok – Basi/Kedaluwarsa', 'pengeluaran', '6-1403', 61],
        'OPN-HILANG'   => ['Kerugian Stok – Hilang', 'pengeluaran', '6-1404', 62],
        'OPN-SUSUT'    => ['Penyusutan Stok', 'pengeluaran', '6-1405', 63],
        'OPN-SELISIH'  => ['Selisih Persediaan', 'pengeluaran', '6-1406', 64],
        'OPN-SAMPEL'   => ['Promosi & Sampel (stok)', 'pengeluaran', '6-1501', 65],
        'OPN-KONSUMSI' => ['Makan Karyawan (stok)', 'pengeluaran', '6-1303', 66],
        'OPN-LEBIH'    => ['Selisih Lebih Persediaan', 'pemasukan', '7-1103', 67],
        'OPN-PULIH'    => ['Pemulihan Kerugian Stok', 'pemasukan', '7-1104', 68],
    ];

    public static function pastikan(): void
    {
        if (! Schema::hasTable('chart_of_accounts') || ! Schema::hasTable('kategori_transaksis')) {
            return;
        }
        $now = now();

        foreach (self::AKUN as $kode => [$nama, $tipe, $saldo, $ket]) {
            $parent = $tipe === 'pendapatan_lain' ? '7-0000' : '6-0000';
            $ada = DB::table('chart_of_accounts')->where('kode', $kode)->first();
            if ($ada) {
                if ($ada->deleted_at) {
                    DB::table('chart_of_accounts')->where('id', $ada->id)->update(['deleted_at' => null, 'updated_at' => $now]);
                }
                continue;
            }
            // urutan: tepat sesudah akun sebelumnya di kelompok yang sama
            $urutan = (int) DB::table('chart_of_accounts')->where('kode', '<', $kode)->where('kode', 'like', substr($kode, 0, 1) . '-%')->max('urutan') + 1;
            DB::table('chart_of_accounts')->insert([
                'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'parent_kode' => $parent,
                'saldo_normal' => $saldo, 'level' => 2, 'is_leaf' => true, 'urutan' => $urutan,
                'keterangan' => $ket, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::KATEGORI as $kode => [$nama, $tipe, $akun, $urutan]) {
            $ada = DB::table('kategori_transaksis')->where('kode', $kode)->first();
            if ($ada) {
                DB::table('kategori_transaksis')->where('id', $ada->id)->update([
                    'kode_akun_coa' => $akun, 'is_system' => true, 'is_active' => true, 'deleted_at' => null, 'updated_at' => $now,
                ]);
                continue;
            }
            DB::table('kategori_transaksis')->insert([
                'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'is_system' => true, 'is_active' => true,
                'urutan' => $urutan, 'kode_akun_coa' => $akun,
                'tipe_biaya' => $tipe === 'pengeluaran' ? 'variabel' : null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public static function kategoriId(string $kode): int
    {
        $id = DB::table('kategori_transaksis')->where('kode', $kode)->whereNull('deleted_at')->value('id');
        if (! $id) {
            self::pastikan();
            $id = DB::table('kategori_transaksis')->where('kode', $kode)->value('id');
        }

        return (int) $id;
    }
}
