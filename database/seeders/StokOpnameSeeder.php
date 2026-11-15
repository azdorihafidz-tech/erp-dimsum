<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use App\Support\StokOpnameAkun;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Stok Opname (RANCANGAN_STOK_OPNAME.md) — izin, akun COA & kategori
 * transaksi, panduan, tooltip. ADDITIVE & idempotent — aman di production:
 * centangan role bawaan HANYA dipasang saat izin pertama kali dibuat.
 */
class StokOpnameSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'stok_opname.view',       'display_name' => 'Lihat Stok Opname (daftar, berita acara)',                      'group' => 'stok'],
        ['name' => 'stok_opname.hitung',     'display_name' => 'Hitung Stok Opname (isi jumlah fisik)',                         'group' => 'stok'],
        ['name' => 'stok_opname.ajukan',     'display_name' => 'Mulai & Periksa Stok Opname (lihat selisih, rincian, ajukan)',  'group' => 'stok'],
        ['name' => 'stok_opname.setujui',    'display_name' => 'Setujui / Tolak Stok Opname (stok & keuangan disesuaikan)',     'group' => 'stok'],
        ['name' => 'stok_opname.batal',      'display_name' => 'Batalkan Stok Opname (sebelum disetujui)',                      'group' => 'stok'],
        ['name' => 'stok_opname.stok_rusak', 'display_name' => 'Kelola Stok Rusak (tindak lanjut barang rusak)',                'group' => 'stok'],
        ['name' => 'stok_opname.ganti_rugi', 'display_name' => 'Kelola Ganti Rugi Karyawan (barang hilang)',                    'group' => 'stok'],
        ['name' => 'stok_opname.laporan',    'display_name' => 'Laporan Kerugian Stok',                                        'group' => 'laporan'],
    ];

    public const ROLE_DEFAULTS = [
        'admin_pusat'       => ['stok_opname.view', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui', 'stok_opname.batal', 'stok_opname.stok_rusak', 'stok_opname.ganti_rugi', 'stok_opname.laporan'],
        'admin_gudang'      => ['stok_opname.view', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.batal', 'stok_opname.stok_rusak'],
        'manajer_cabang'    => ['stok_opname.view', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.batal', 'stok_opname.stok_rusak', 'stok_opname.laporan'],
        'kasir'             => ['stok_opname.view', 'stok_opname.hitung'],
        'operator_produksi' => ['stok_opname.view', 'stok_opname.hitung'],
        'helper'            => ['stok_opname.hitung'],
    ];

    public function run(): void
    {
        StokOpnameAkun::pastikan();
        $baru = $this->izin();
        $this->panduan();
        $this->tooltip();

        if ($baru) {
            $this->command?->info('   Izin baru dibuat: ' . implode(', ', $baru));
        }
        $this->command?->info('✅ Stok Opname: izin, akun, panduan & tooltip (idempotent).');
    }

    /** @return string[] izin yang baru dibuat */
    private function izin(): array
    {
        $baru = [];
        foreach (self::PERMISSIONS as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], ['display_name' => $p['display_name'], 'group' => $p['group']]);
            if (! $perm->wasRecentlyCreated) {
                continue;
            }
            $baru[] = $p['name'];
            $rows = [];
            foreach (self::ROLE_DEFAULTS as $role => $names) {
                if (in_array($p['name'], $names, true)) {
                    $rows[] = ['role' => $role, 'permission_id' => $perm->id];
                }
            }
            DB::table('role_permissions')->insertOrIgnore($rows);
        }

        Cache::forget('all_permission_names');
        foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        return $baru;
    }

    private function panduan(): void
    {
        Panduan::updateOrCreate(['slug' => 'stok-opname'], [
            'judul'  => 'Stok Opname (Hitung Fisik Stok)',
            'konten' => self::PANDUAN,
            'modul'  => 'stok',
            'urutan' => 44,
            'aktif'  => true,
        ]);
    }

    private function tooltip(): void
    {
        foreach ([
            ['stok_opname.lokasi', 'Lokasi', 'Gudang/cabang yang stoknya dihitung. Hanya barang milik lokasi ini yang masuk daftar.'],
            ['stok_opname.cakupan', 'Cakupan', 'Semua barang, per kategori, atau barang tertentu. Satu barang hanya boleh ada di satu opname yang belum selesai di lokasi yang sama.'],
            ['stok_opname.penghitung', 'Penghitung', 'Orang yang mengisi jumlah fisik. Hanya user berizin "Hitung Stok Opname" yang punya akses ke lokasi ini yang muncul.'],
            ['stok_opname.hitung_buta', 'Hitung buta', 'Kalau nyala, penghitung tidak melihat stok sistem — supaya angka yang diisi benar-benar hasil hitungan. Pemeriksa & penyetuju tetap melihat selisih. Diatur di Pengaturan Umum.'],
            ['stok_opname.scan', 'Scan / ketik kode', 'Ketik kode barang lalu Enter, atau pakai scanner barcode yang bekerja seperti keyboard. Sekali scan = jumlah fisik +1.'],
            ['stok_opname.penyebab', 'Penyebab selisih', 'Barang yang kurang wajib dirinci penyebabnya (boleh dipecah, mis. 2 rusak + 1 hilang). Rusak/Basi/Freezer mati masuk Stok Rusak; semua kerugian dicatat sebagai beban tanpa mengurangi kas.'],
        ] as $i => [$key, $title, $content]) {
            Tooltip::updateOrCreate(['key' => $key], ['title' => $title, 'content' => $content, 'modul' => 'stok', 'aktif' => true, 'urutan' => 120 + $i]);
        }
    }

    public const PANDUAN = <<<'MARKDOWN'
## Tujuan
Mencocokkan stok di sistem dengan stok fisik di gudang/cabang. Selisihnya dicatat lengkap dengan penyebab, dan kerugiannya muncul di **Laba Rugi** sebagai beban — **tanpa mengurangi kas**.

Selama opname, lokasi **tetap boleh berjualan**. Stok sistem dicatat per barang **saat barang itu dihitung**, dan yang diterapkan saat disetujui hanya **selisihnya**. Contoh: jam 08.00 sistem 100, fisik 95 (selisih −5). Jam 10.00 POS memakai 20 (stok 80). Saat disetujui stok menjadi 80 − 5 = **75**.

## Langkah-langkah

### 1. Mulai
1. **Stok & Gudang → Stok Opname → Mulai Opname**.
2. Pilih **lokasi**, **cakupan** (semua barang / per kategori / barang tertentu), dan **penghitung**.
3. Klik **Mulai Opname**. Nomor otomatis `SO-TahunBulan-Urut`.

Satu barang hanya boleh ada di satu opname yang belum selesai per lokasi. Selama opname berjalan, barang itu **tidak bisa** diubah lewat Penyesuaian Stok / Hapus Stok.

### 2. Hitung
- Isi **jumlah fisik** tiap barang — tersimpan otomatis (tanda ✓ hijau).
- Atau **ketik/scan kode barang lalu Enter**: jumlah +1 tiap kali.
- Satuan berat/volume (kg, gram, ons, liter, ml) boleh desimal; satuan lain (pcs, porsi, pack…) bilangan bulat.
- **Hitung buta**: kalau nyala, penghitung tidak melihat stok sistem.

### 3. Periksa (pemeriksa)
- Lihat **stok sistem saat dihitung** dan **selisih**. Pakai saringan "Ada selisih".
- **Minta hitung ulang** kalau angkanya meragukan.
- **Tidak dihitung**: keluarkan barang yang memang tidak bisa dihitung sekarang (alasan wajib; stoknya tidak disesuaikan).
- **Rincikan penyebab** untuk setiap barang yang kurang. Satu barang boleh dipecah; jumlahnya harus sama dengan selisih.

| Penyebab | Stok | Dicatat sebagai |
|---|---|---|
| Rusak (pecah/penyok) | keluar ke **Stok Rusak** | Beban Kerugian Stok – Rusak |
| Basi / Kedaluwarsa | keluar ke **Stok Rusak** | Beban Kerugian Stok – Basi/Kedaluwarsa |
| Freezer / pendingin mati | keluar ke **Stok Rusak** | Beban Kerugian Stok – Rusak |
| Hilang | keluar | Beban Kerugian Stok – Hilang |
| Susut (berat, thawing) | keluar | Beban Penyusutan Stok |
| Sampel / tester | keluar | Beban Promosi & Sampel |
| Makan karyawan | keluar | Beban Konsumsi |
| Salah catat | keluar | Selisih Persediaan |
| Lebih (fisik > sistem) | masuk (batch baru, harga pokok rata-rata) | Selisih Lebih Persediaan (pendapatan lain) |

**Foto wajib** untuk Rusak, Basi/Kedaluwarsa, Freezer mati, dan untuk Hilang yang nilainya mencapai batas di Pengaturan Umum (bawaan Rp 200.000 per barang).

### 4. Ajukan → Setujui / Tolak
- Pemeriksa klik **Ajukan Persetujuan** (semua barang harus sudah dihitung dan dirinci).
- Penyetuju klik **Setujui** atau **Tolak** (alasan wajib; opname kembali ke tahap hitung).
- Orang yang ikut menghitung **tidak bisa** menyetujui opname itu sendiri (kecuali Owner).

### 5. Saat disetujui
- Stok disesuaikan sebesar selisih, nilai kerugian = **harga pokok FIFO** (batch tertua).
- Keuangan mendapat satu baris per penyebab **tanpa kas** (tidak bisa diedit/dihapus dari Kas & Transaksi).
- Barang Rusak/Basi/Freezer mati masuk **Stok Rusak** untuk ditindaklanjuti.
- Opname dikunci.

## Catatan Penting
- **Waste produksi** (hasil nyata lebih sedikit dari rencana) dicatat di menu **Produksi**, bukan lewat opname — biayanya sudah masuk modal hasil produksi.
- Kalau stok sudah terjual/terpakai sehingga tidak cukup untuk dikurangi saat disetujui, persetujuan ditolak. Tolak opname, minta hitung ulang barang itu.
- Kalau hari ini sudah **Closing Harian / Tutup Buku**, persetujuan ditolak. Buka kunci Closing hari ini atau setujui besok.
- **Batalkan** hanya bisa sebelum disetujui; stok & keuangan tidak berubah.

## Troubleshooting
- **Barang tidak ada di daftar saat memulai**: barang harus milik lokasi itu, bertipe Bahan Baku/Kemasan/Tambahan Gratis, dan dicentang *Lacak Stok*.
- **"Sedang dihitung di Stok Opname SO-…" saat Penyesuaian Stok**: selesaikan atau batalkan opname itu dulu.
- **Tombol Ajukan tidak aktif**: lihat kotak kuning "Sebelum bisa diajukan".
MARKDOWN;
}
