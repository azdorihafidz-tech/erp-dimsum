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
        Panduan::updateOrCreate(['slug' => 'stok-rusak'], [
            'judul'  => 'Stok Rusak (tindak lanjut barang rusak)',
            'konten' => self::PANDUAN_RUSAK,
            'modul'  => 'stok',
            'urutan' => 45,
            'aktif'  => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'ganti-rugi-stok'], [
            'judul'  => 'Ganti Rugi Stok (Karyawan)',
            'konten' => self::PANDUAN_GANTI_RUGI,
            'modul'  => 'hr',
            'urutan' => 47,
            'aktif'  => true,
        ]);
        // Bagian tambahan di panduan yang sudah ada (penanda supaya tidak dobel)
        $this->tambahBagian('penggajian', '<!-- STOK-OPNAME-452 -->', "## Potongan Ganti Rugi Stok\nKaryawan yang dibebani ganti rugi barang hilang lewat **Stok Opname** dengan cara **potong gaji** otomatis mendapat baris **Potongan Ganti Rugi Stok** di slip bulan berikutnya: dicicil sesuai jumlah bulan yang dipilih, tidak pernah melebihi gaji bersih (kekurangannya digeser ke bulan berikutnya), dan tidak dobel kalau slip draft dihitung ulang. Saat slip **Dibayar**, potongan dicatat sebagai Beban Gaji (gaji kotor) sekaligus Pemulihan Kerugian Stok — kas tetap hanya berkurang sebesar gaji bersih. **Batal Bayar** ikut membatalkannya. Sisa tagihan terlihat di menu SDM → Ganti Rugi Stok.");
        $this->tambahBagian('pengaturan-umum', '<!-- STOK-OPNAME-452 -->', "## Stok Opname\nKartu **Stok Opname** di halaman ini mengatur: **hitung buta** untuk opname baru (penghitung tidak melihat stok sistem), **batas nilai barang hilang yang wajib foto** (bawaan Rp 200.000 per barang; 0 = semua wajib foto), dan **pengingat** lokasi yang belum opname bulan ini (nyala/mati, mulai H-1…H-10 sebelum akhir bulan, jam). Setiap perubahan tercatat di log aktivitas.");
        Panduan::updateOrCreate(['slug' => 'laporan-kerugian-stok'], [
            'judul'  => 'Laporan Kerugian Stok',
            'konten' => self::PANDUAN_LAPORAN,
            'modul'  => 'laporan',
            'urutan' => 46,
            'aktif'  => true,
        ]);
    }

    private function tambahBagian(string $slug, string $penanda, string $isi): void
    {
        $panduan = Panduan::where('slug', $slug)->first();
        if (! $panduan || str_contains((string) $panduan->konten, $penanda)) {
            return;
        }
        $panduan->update(['konten' => rtrim((string) $panduan->konten) . "\n\n{$penanda}\n{$isi}\n"]);
    }

    private function tooltip(): void
    {
        foreach ([
            ['stok_opname.lokasi', 'Lokasi', 'Gudang/cabang yang stoknya dihitung. Hanya barang milik lokasi ini yang masuk daftar.'],
            ['stok_opname.cakupan', 'Cakupan', 'Semua barang, per kategori, atau barang tertentu. Satu barang hanya boleh ada di satu opname yang belum selesai di lokasi yang sama.'],
            ['stok_opname.penghitung', 'Penghitung', 'Orang yang mengisi jumlah fisik. Hanya user berizin "Hitung Stok Opname" yang punya akses ke lokasi ini yang muncul.'],
            ['stok_opname.hitung_buta', 'Hitung buta', 'Kalau nyala, penghitung tidak melihat stok sistem — supaya angka yang diisi benar-benar hasil hitungan. Pemeriksa & penyetuju tetap melihat selisih. Diatur di Pengaturan Umum.'],
            ['stok_opname.scan', 'Scan / ketik kode', 'Ketik kode barang lalu Enter, atau pakai scanner barcode yang bekerja seperti keyboard. Sekali scan = jumlah fisik +1.'],
            ['laporan_kerugian_stok.kotor', 'Kerugian kotor', 'Jumlah nilai barang yang kurang dari semua Stok Opname yang DISETUJUI di periode ini (harga pokok FIFO). Tidak mengurangi kas.'],
            ['laporan_kerugian_stok.bersih', 'Kerugian bersih', 'Kerugian kotor dikurangi uang yang diterima kembali (ganti rugi karyawan, uang pemasok), barang yang kembali (diganti pemasok / dipakai lagi), dan kelebihan stok.'],
            ['stok_rusak.tindakan', 'Tindak lanjut stok rusak', 'Diganti barang / Dipakai lagi: barang kembali ke stok jual, kerugian dibatalkan. Dibayar uang / Dipotong tagihan: uang masuk kas sebagai Pemulihan Kerugian Stok. Dimusnahkan: foto wajib + Berita Acara Pemusnahan.'],
            ['ganti_rugi.cara', 'Ganti rugi karyawan', 'Bebankan nilai barang hilang ke karyawan. Tunai: kas lokasi bertambah sekarang. Potong gaji: dicicil 1–12 bulan mulai slip bulan depan, tidak melebihi gaji bersih. Nilai bawaan = harga pokok; nilai lain wajib alasan.'],
            ['pengaturan_umum.stok_opname', 'Pengaturan Stok Opname', 'Hitung buta untuk opname baru, batas nilai barang hilang yang wajib foto, dan pengingat lokasi yang belum opname di akhir bulan.'],
            ['stok_opname.akurasi', 'Akurasi', 'Persentase barang yang jumlah fisiknya sama dengan stok sistem (barang cocok ÷ barang dihitung) pada opname yang disetujui. Berita Acara juga menampilkan akurasi berdasarkan nilai rupiah.'],
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

### Berita Acara
Tombol **Berita Acara** (PDF) dan ikon Excel di halaman opname: kepala, rincian per barang (sistem, fisik, selisih, harga pokok, nilai, penyebab), ringkasan & **akurasi %**, lampiran foto, dan kolom tanda tangan Penghitung / Pemeriksa / Penyetuju. Sebelum disetujui dokumen bertanda **SEMENTARA**. Penghitung yang hitung buta baru bisa mengunduhnya setelah opname selesai.

### 5. Saat disetujui
- Stok disesuaikan sebesar selisih, nilai kerugian = **harga pokok FIFO** (batch tertua).
- Keuangan mendapat satu baris per penyebab **tanpa kas** (tidak bisa diedit/dihapus dari Kas & Transaksi).
- Barang Rusak/Basi/Freezer mati masuk **Stok Rusak** untuk ditindaklanjuti.
- Opname dikunci.

### Dashboard & pengingat
**Stok Opname → Dashboard**: status bulan ini per lokasi (sudah / menunggu persetujuan / sedang dihitung / **belum** = merah), daftar menunggu persetujuan, kerugian per penyebab dibanding bulan lalu, grafik akurasi 6 bulan per lokasi (dengan tabel), 10 barang paling sering selisih, stok rusak tertahan, dan ringkasan ganti rugi.

Menjelang akhir bulan (bawaan **H-3, jam 08.00**, diatur di Pengaturan Umum) lokasi yang belum opname bulan itu mendapat **notifikasi** ke kepala lokasi (kalau kosong: Manajer Cabang lokasi itu) dan Owner — satu kali per orang per hari — dan **angka merah** di menu Stok Opname.

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

    public const PANDUAN_RUSAK = <<<'MARKDOWN'
## Tujuan
Barang yang di Stok Opname dicatat **Rusak**, **Basi/Kedaluwarsa**, atau **Freezer mati** keluar dari stok jual dan ditahan di **Stok Rusak** (tidak bisa dijual, dipakai resep, atau ditransfer). Kerugiannya sudah dicatat saat opname disetujui. Di sini Anda menentukan nasib barang itu.

## Langkah-langkah
1. Buka **Stok & Gudang → Stok Rusak**. Bawaan: barang yang masih tertahan di lokasi aktif.
2. Klik **Tindak lanjut** pada barang, pilih tindakan dan jumlahnya (boleh sebagian).
3. Klik **Simpan**.

| Tindakan | Stok | Keuangan |
|---|---|---|
| Kembali ke pemasok — diganti barang | barang masuk lagi ke stok jual (harga pokok semula) | kerugian **dibatalkan** (tanpa kas) |
| Kembali ke pemasok — dibayar uang | — | uang masuk ke **kas yang dipilih** (Pemulihan Kerugian Stok) |
| Kembali ke pemasok — dipotong tagihan | — | nomor PO/faktur **wajib**; dicatat sebagai uang masuk ke kas yang dipakai membayar PO itu |
| Dimusnahkan | — | tidak ada catatan baru. **Foto wajib**, **Berita Acara Pemusnahan** (PDF) dibuat otomatis |
| Dipakai lagi | barang kembali ke stok jual (harga pokok semula) | kerugian **dibatalkan** (tanpa kas) |

## Catatan Penting
- **Dipakai lagi** hanya untuk penyebab **Rusak** (kemasan penyok/pecah, isi aman). Barang basi, kedaluwarsa, atau kena freezer mati hanya bisa dimusnahkan atau dikembalikan ke pemasok.
- **Dipotong tagihan**: bayar PO tetap penuh di sistem. Karena potongan dicatat sebagai uang masuk ke kas yang sama, saldo kas bersihnya sama dengan uang yang benar-benar keluar.
- Kalau hari ini sudah Closing Harian / Tutup Buku, tindakan yang mencatat keuangan ditolak — buka kunci atau lakukan besok.
- Semua tindakan tercatat di riwayat baris itu dan di Laporan → Kerugian Stok.
MARKDOWN;

    public const PANDUAN_GANTI_RUGI = <<<'MARKDOWN'
## Tujuan
Membebankan nilai barang **Hilang** dari Stok Opname kepada karyawan, dibayar tunai atau dipotong dari gaji (bisa dicicil).

## Langkah-langkah
1. Buka **Stok Opname** yang sudah **disetujui** dan punya barang Hilang. Di bagian bawah ada kotak **Ganti rugi karyawan**.
2. Pilih **karyawan**, isi **nilai** (bawaan = harga pokok barang hilang yang belum dibebankan), pilih **cara**:
   - **Tunai** — pilih kas lokasi; uang langsung masuk kas.
   - **Potong gaji** — pilih dicicil 1–12 bulan; potongan masuk slip mulai bulan depan.
3. Klik **Bebankan**. Untuk membagi ke beberapa orang, ulangi dengan nilai masing-masing (nilai lain dari bawaan wajib diberi alasan, mis. "dibagi 2 orang").
4. Pantau di **SDM → Ganti Rugi Stok**: nilai, sudah diterima, sisa, dan potongan per slip.

## Catatan Penting
- **Tunai**: kas bertambah dan tercatat sebagai **Pemulihan Kerugian Stok** (mengurangi rugi bersih).
- **Potong gaji**: potongan tidak pernah melebihi gaji bersih; kekurangannya otomatis digeser ke bulan berikutnya. Slip draft yang dihitung ulang tidak membuat potongan dobel.
- Saat slip **Dibayar**, potongan dicatat sebagai **Beban Gaji** (sehingga beban gaji = gaji kotor) dan **Pemulihan Kerugian Stok**; kas tetap hanya berkurang sebesar gaji bersih. **Batal Bayar** slip ikut membatalkannya.
- **Batal** ganti rugi hanya bisa kalau belum ada pembayaran sama sekali (tunai langsung dianggap lunas). Potongan yang sudah masuk slip yang disetujui harus dibatalkan dulu dari slipnya.
- Izin bawaan hanya Admin Pusat (dan Owner).
MARKDOWN;

    public const PANDUAN_LAPORAN = <<<'MARKDOWN'
## Tujuan
Melihat berapa nilai barang yang hilang/rusak/basi dalam satu periode, dari mana penyebabnya, dan berapa yang sudah kembali.

## Langkah-langkah
1. Buka **Laporan → Kerugian Stok**.
2. Pilih **lokasi** (atau semua lokasi) dan **periode** (bawaan: awal bulan sampai hari ini).
3. Klik **Tampilkan**. Unduh dengan tombol **Excel** atau **PDF**.

## Isi laporan
- **Kerugian kotor**: nilai barang yang kurang (harga pokok FIFO) dari opname yang **disetujui** di periode itu.
- **Dipulihkan**: uang yang diterima kembali (ganti rugi karyawan, uang dari pemasok) + kerugian yang dibatalkan karena barang kembali (diganti pemasok / dipakai lagi).
- **Kelebihan stok**: nilai barang yang fisiknya lebih banyak dari sistem.
- **Kerugian bersih** = kotor − dipulihkan − kelebihan.
- Rincian **per penyebab**, **per lokasi** (jumlah opname & akurasi rata-rata), **per barang** (50 terbesar), **stok rusak tertahan** & tindak lanjutnya, dan **ganti rugi karyawan**.

## Catatan Penting
- Kerugian stok tidak mengurangi kas. Di **Laba Rugi Formal** kerugian tampil per akun: Kerugian Stok – Rusak / Basi / Hilang, Penyusutan Stok, Promosi & Sampel, Selisih Persediaan; kelebihan di Selisih Lebih Persediaan.
- Periode dihitung dari **tanggal persetujuan** opname, bukan tanggal mulai.
- Role tanpa akses semua cabang hanya melihat lokasinya sendiri.
MARKDOWN;
}
