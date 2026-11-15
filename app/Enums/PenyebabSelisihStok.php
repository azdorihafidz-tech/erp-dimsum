<?php

namespace App\Enums;

/**
 * Penyebab selisih Stok Opname (RANCANGAN_STOK_OPNAME.md bagian 4).
 * Semua kerugian dicatat tanpa kas; nilai = harga pokok FIFO.
 * Kategori transaksi & akun COA dibuat oleh App\Support\StokOpnameAkun.
 */
enum PenyebabSelisihStok: string
{
    case Rusak = 'rusak';
    case Basi = 'basi';
    case FreezerMati = 'freezer_mati';
    case Hilang = 'hilang';
    case Susut = 'susut';
    case Sampel = 'sampel';
    case MakanKaryawan = 'makan_karyawan';
    case SalahCatat = 'salah_catat';
    case Lebih = 'lebih';

    public function label(): string
    {
        return match ($this) {
            self::Rusak         => 'Rusak (pecah/penyok kemasan)',
            self::Basi          => 'Basi / Kedaluwarsa',
            self::FreezerMati   => 'Freezer / pendingin mati',
            self::Hilang        => 'Hilang',
            self::Susut         => 'Susut (berat berkurang, thawing)',
            self::Sampel        => 'Sampel / tester / promosi',
            self::MakanKaryawan => 'Makan karyawan',
            self::SalahCatat    => 'Salah catat',
            self::Lebih         => 'Lebih (fisik lebih banyak)',
        };
    }

    public function labelPendek(): string
    {
        return match ($this) {
            self::Rusak         => 'Rusak',
            self::Basi          => 'Basi/Kedaluwarsa',
            self::FreezerMati   => 'Freezer mati',
            self::Hilang        => 'Hilang',
            self::Susut         => 'Susut',
            self::Sampel        => 'Sampel',
            self::MakanKaryawan => 'Makan karyawan',
            self::SalahCatat    => 'Salah catat',
            self::Lebih         => 'Lebih',
        };
    }

    /** Kode kategori transaksi keuangan. */
    public function kodeKategori(): string
    {
        return match ($this) {
            self::Rusak, self::FreezerMati => 'OPN-RUSAK',
            self::Basi                     => 'OPN-BASI',
            self::Hilang                   => 'OPN-HILANG',
            self::Susut                    => 'OPN-SUSUT',
            self::Sampel                   => 'OPN-SAMPEL',
            self::MakanKaryawan            => 'OPN-KONSUMSI',
            self::SalahCatat               => 'OPN-SELISIH',
            self::Lebih                    => 'OPN-LEBIH',
        };
    }

    /** Barang keluar ke Stok Rusak (ditahan) untuk ditindaklanjuti. */
    public function keStokRusak(): bool
    {
        return in_array($this, [self::Rusak, self::Basi, self::FreezerMati], true);
    }

    /** Foto selalu wajib (Hilang: wajib kalau nilainya ≥ batas di Pengaturan). */
    public function selaluWajibFoto(): bool
    {
        return $this->keStokRusak();
    }

    public function menambahStok(): bool
    {
        return $this === self::Lebih;
    }

    /** Pilihan untuk barang yang fisiknya KURANG dari sistem. */
    public static function untukKurang(): array
    {
        return array_values(array_filter(self::cases(), fn ($c) => $c !== self::Lebih));
    }
}
