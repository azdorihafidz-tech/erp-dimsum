<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Panduan + tooltip Perbaikan Harga & fitur lanjutan (CLAUDE.md 4.38,
 * 2026-10-18). Idempotent: tiap section ber-marker sendiri, tidak dobel
 * kalau dijalankan ulang; tooltip pakai updateOrCreate.
 */
class Perbaikan438PanduanSeeder extends Seeder
{
    public function run(): void
    {
        $this->appendSections($this->sectionsTahapA(), '<!-- PERBAIKAN438-A -->');
        $this->tooltips($this->tooltipsTahapA());

        $this->panduanBaru('cabang', 'Pengaturan Cabang', 'master', $this->panduanCabang());
        $this->appendSections($this->sectionsTahapB(), '<!-- PERBAIKAN438-B -->');
        $this->tooltips($this->tooltipsTahapB());

        $this->appendSections($this->sectionsTahapC(), '<!-- PERBAIKAN438-C -->');
        $this->tooltips($this->tooltipsTahapC());

        $this->panduanBaru('role-hak-akses', 'Role & Hak Akses', 'master', $this->panduanRole());
        $this->tooltips($this->tooltipsTahapD());

        $this->appendSections($this->sectionsTahapF(), '<!-- PERBAIKAN438-F -->');
        $this->tooltips($this->tooltipsTahapF());

        $this->command?->info('✅ Panduan & tooltip Perbaikan 4.38 tersimpan (idempotent).');
    }

    private function appendSections(array $sections, string $marker): void
    {
        foreach ($sections as $slug => $konten) {
            $panduan = Panduan::where('slug', $slug)->first();
            if (! $panduan || str_contains((string) $panduan->konten, $marker)) {
                continue;
            }
            $panduan->konten = rtrim((string) $panduan->konten) . "\n\n" . $marker . "\n" . trim($konten) . "\n";
            $panduan->save();
        }
    }

    /** Panduan baru — firstOrCreate supaya editan Owner di menu Panduan tidak tertimpa. */
    private function panduanBaru(string $slug, string $judul, string $modul, string $konten): void
    {
        Panduan::firstOrCreate(['slug' => $slug], [
            'judul' => $judul, 'konten' => $konten, 'modul' => $modul, 'urutan' => 50, 'aktif' => true,
        ]);
    }

    private function tooltips(array $rows): void
    {
        foreach ($rows as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'],
                'modul' => $t['modul'], 'aktif' => true, 'urutan' => 900 + $i,
            ]);
        }
    }

    // =====================================================================
    // Tahap A — Harga
    // =====================================================================

    private function sectionsTahapA(): array
    {
        return [
            'bahan-baku' => <<<'MD'
## 🔄 Update 2026-10-18 — Harga Standar, Harga PO Terakhir, Harga per Outlet
- **Harga Standar** (dulu "Harga Beli / HPP") adalah harga patokan Anda per **satuan pakai** (mis. per pcs). Harga ini **tidak lagi berubah otomatis** saat PO diterima.
- Kalau barang punya **Unit Beli** (mis. pack isi 50), muncul kolom **"Atau isi harga per pack"** — isi harga per pack, sistem menghitung harga per pcs otomatis (contoh: Rp 5.000 / pack isi 50 = Rp 100 / pcs).
- **Harga PO Terakhir** tampil di bawahnya sebagai informasi (harga dari PO terakhir yang diterima + tanggalnya). Di daftar Bahan Baku tampil di kolom "PO Terakhir".
- **Harga Beli per Outlet** (opsional): isi kalau harga beli di outlet tertentu berbeda (mis. HO Rp 100, Outlet 1 Rp 150). Kosongkan = memakai Harga Standar. Harga ini otomatis menjadi harga awal saat outlet itu membuat PO.
- **HPP penjualan tetap FIFO**: stok dari PO lama dijual dengan harga PO lama, stok dari PO baru dengan harga PO baru — tidak terpengaruh Harga Standar.
MD,
            'pembelian' => <<<'MD'
## 🔄 Update 2026-10-18 — Harga Otomatis & Unit Pack
- Saat memilih barang, **harga terisi otomatis** dari Harga Beli outlet tujuan (kalau diisi di master) atau Harga Standar.
- Kalau unit yang dipilih **pack/dus/dll**, harga otomatis **dikali isi** (contoh: Rp 100/pcs × isi 50 = Rp 5.000 per pack). Ganti unit pcs ↔ pack → harga ikut menyesuaikan.
- Ganti **Lokasi Tujuan** → harga baris yang belum Anda ubah manual ikut harga outlet baru.
- PO yang diterima **tidak lagi mengubah Harga Standar** di master — harganya tercatat sebagai "Harga PO Terakhir" (info) dan sebagai harga batch FIFO stok.
MD,
            'produk-jual' => <<<'MD'
## 🔄 Update 2026-10-18 — Harga Varian per Outlet
Varian yang **tidak** diisi harga khusus sekarang mengikuti **harga outlet** (kolom harga di tabel ketersediaan outlet), bukan harga jual default. Varian yang diisi harga khusus tetap memakai harga khususnya di semua outlet.
MD,
            'transfer-stok' => <<<'MD'
## 🔄 Update 2026-10-18 — Harga Modal Stok Transfer
Stok yang diterima outlet dari transfer sekarang dinilai dengan **modal FIFO yang benar-benar keluar dari lokasi asal** (dulu memakai harga master). Membatalkan transfer yang sudah dikirim juga mengembalikan stok dengan modal yang sama (dulu tercatat Rp 0). Transfer biasa tetap **tanpa pembayaran**.
MD,
        ];
    }

    // =====================================================================
    // Tahap B — Wajib bukti bayar per cabang
    // =====================================================================

    private function panduanCabang(): string
    {
        return <<<'MD'
## Tujuan
Mengelola data lokasi usaha (Gudang Pusat & outlet): identitas, lokasi absensi, dan pengaturan POS per outlet.

## Langkah-langkah
1. Buka menu **Cabang**, klik **Tambah Cabang** atau **Edit** pada cabang yang ada.
2. Isi nama, kode, tipe (Gudang Pusat / Outlet), alamat, dan titik lokasi + radius absensi.
3. Di bagian **Pengaturan POS**: aktif/nonaktifkan tipe transaksi (Dine-in, Takeaway, Frozen), input nomor meja, Takeaway Fee, dan Service Charge.
4. **Wajib upload bukti pembayaran di POS**: nyalakan saklar **Transfer** dan/atau **QRIS** kalau kasir harus mengunggah foto bukti saat pelanggan membayar dengan metode itu. Matikan kalau boleh tanpa bukti.
5. Klik **Simpan**.

## Catatan Penting
- Saklar wajib bukti berlaku untuk transaksi POS baru, bayar Bill Tersimpan, dan split payment (kalau salah satu bagiannya Transfer/QRIS yang wajib).
- Aturan dicek di aplikasi **dan** di server, jadi tidak bisa dilewati.
- Default saklar: **aktif** (sama dengan perilaku sebelumnya).

## Troubleshooting
- **Kasir tidak bisa bayar Transfer/QRIS tanpa foto?** Saklar wajib bukti outlet itu aktif — upload foto bukti atau matikan saklarnya.
- **Perubahan belum terasa di POS?** Muat ulang halaman POS.
MD;
    }

    private function sectionsTahapB(): array
    {
        return [
            'pos' => <<<'MD'
## 🔄 Update 2026-10-18 — Bukti Pembayaran Transfer/QRIS
Wajib atau tidaknya upload bukti pembayaran sekarang diatur **per outlet** di menu Cabang (saklar Transfer dan QRIS). Tanda bintang merah di kolom Bukti Pembayaran muncul hanya kalau wajib. Berlaku juga untuk split payment dan tombol **Bayar** pada Bill Tersimpan (modalnya sekarang punya kolom upload bukti).
MD,
        ];
    }

    private function tooltipsTahapB(): array
    {
        return [
            ['key' => 'cabang.wajib_bukti', 'modul' => 'cabang', 'title' => 'Wajib Bukti Pembayaran',
                'content' => 'Aktif = kasir wajib upload foto bukti untuk pembayaran Transfer/QRIS di outlet ini (termasuk split payment & bayar Bill Tersimpan). Nonaktif = boleh tanpa bukti.'],
        ];
    }

    // =====================================================================
    // Tahap C — PO cabang ke Gudang Pusat tampil di POS gudang
    // =====================================================================

    private function sectionsTahapC(): array
    {
        return [
            'supplier' => <<<'MD'
## 🔄 Update 2026-10-18 — Supplier Internal
Isi kolom **Supplier Internal (lokasi kita)** kalau supplier ini adalah lokasi Anda sendiri, mis. buat supplier "Gudang Pusat" lalu pilih lokasi **Gudang Pusat**. Setelah itu, PO cabang ke supplier ini otomatis tampil di POS Gudang Pusat.
MD,
            'pembelian' => <<<'MD'
## 🔄 Update 2026-10-18 — PO ke Gudang Pusat (Supplier Internal)
1. Tanyakan dulu harga jual ke gudang, lalu buat PO dengan supplier internal (mis. "Gudang Pusat — internal").
2. Daftar barang otomatis menampilkan juga **produk yang dijual gudang** (bertanda "produk gudang"). Isi qty & harga sesuai harga gudang.
3. Setelah PO disetujui, PO tampil di **POS Gudang** pada panel **"Pesanan PO dari Cabang"**.
4. Di halaman detail PO muncul kotak **"Nota dari Gudang"** — nomor nota POS gudang & totalnya. Kalau total berbeda dengan PO, **edit atau buat ulang PO** supaya sama, baru terima & bayar seperti PO biasa.
- Cabang tetap bisa PO ke supplier luar seperti biasa (yang tampil hanya bahan/kemasan).
MD,
            'pos' => <<<'MD'
## 🔄 Update 2026-10-18 — Pesanan PO dari Cabang (khusus lokasi supplier internal)
Di POS lokasi yang menjadi supplier internal (mis. Gudang Pusat) muncul panel **"Pesanan PO dari Cabang"** di bagian atas. Klik **Isi Keranjang** → barang & qty dari PO langsung masuk keranjang dengan **harga lokasi ini**, nama pelanggan terisi nama cabang. Baris yang harganya berbeda dengan PO diberi peringatan (cabang yang menyesuaikan PO-nya). Lanjutkan bayar seperti transaksi biasa. PO yang sudah diproses hilang dari panel (muncul lagi kalau notanya dibatalkan).
MD,
        ];
    }

    private function tooltipsTahapC(): array
    {
        return [
            ['key' => 'supplier.cabang_id', 'modul' => 'supplier', 'title' => 'Supplier Internal',
                'content' => 'Pilih lokasi kita (mis. Gudang Pusat) kalau supplier ini adalah lokasi sendiri. PO cabang ke supplier ini tampil di POS lokasi tersebut dan boleh berisi produk yang dijual di sana.'],
            ['key' => 'pos.po_masuk', 'modul' => 'pos', 'title' => 'Pesanan PO dari Cabang',
                'content' => 'PO cabang yang ditujukan ke lokasi ini. Klik Isi Keranjang untuk memasukkan barang & qty-nya ke keranjang dengan harga lokasi ini.'],
            ['key' => 'pembelian.nota_internal', 'modul' => 'pembelian', 'title' => 'Nota dari Supplier Internal',
                'content' => 'Nota POS lokasi supplier yang memproses PO ini. Kalau totalnya berbeda, sesuaikan PO (edit/buat ulang) sebelum diterima & dibayar.'],
        ];
    }

    // =====================================================================
    // Tahap D — Role bebas
    // =====================================================================

    private function panduanRole(): string
    {
        return <<<'MD'
## Tujuan
Mengatur siapa boleh membuka menu apa. Ada **7 role sistem** (Owner, Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi, Helper) dan Anda bisa **menambah role sendiri** (mis. "Kru").

## Langkah-langkah
### Menambah role sendiri
1. Buka **Role & Hak Akses** (khusus Owner), klik **Tambah Role**.
2. Isi **Nama Role** (mis. Kru), deskripsi (opsional), dan pilih apakah role ini **boleh melihat semua cabang** atau hanya cabangnya sendiri.
3. Simpan → role baru muncul sebagai **tab** di bawah. Role baru **belum punya hak akses apa pun**.
4. Buka tab role itu, **centang hak akses** yang dibutuhkan (mis. Lihat Order, Buat Order, Lihat Stok), klik **Simpan Permissions**.
5. Pasang role ke orangnya lewat menu **User** atau **Karyawan** (pilihan role sekarang memuat role buatan).

### Mengubah / menghapus role buatan
- Di kartu **Role Buatan**: **Ubah** untuk ganti nama/akses cabang. **Hapus** hanya bisa kalau tidak ada user yang memakai role itu.

## Catatan Penting
- 7 role sistem tidak bisa dihapus atau diganti namanya.
- Beberapa menu **manajemen** tetap khusus role sistem walau hak aksesnya dicentang untuk role buatan: Cabang, User, Role & Hak Akses, Supplier, Semua Barang (overview), Jenis Menu, Shift, Hari Libur, Registrasi Wajah, kelola Absensi, Penggajian, membuat Evaluasi, dan admin Panduan/Tooltip. Menu operasional (POS, order, stok, PO, keuangan, laporan, closing, Bahan Baku, Produk Jual, Transfer Stok) mengikuti hak akses yang dicentang.
- Perubahan hak akses berlaku paling lambat 5 menit (cache) — atau minta user logout-login.

## Troubleshooting
- **User role buatan dapat pesan 403?** Cek hak akses yang dicentang di tab role tersebut, atau menu itu termasuk menu manajemen khusus role sistem.
- **Tombol Hapus role tidak aktif?** Masih ada user yang memakai role itu — pindahkan user ke role lain dulu.
MD;
    }

    private function tooltipsTahapD(): array
    {
        return [
            ['key' => 'role.custom', 'modul' => 'role', 'title' => 'Role Buatan',
                'content' => 'Role yang Anda tambahkan sendiri (mis. Kru). Hak aksesnya diatur di tab role tersebut. Bisa dihapus kalau tidak dipakai user mana pun.'],
            ['key' => 'role.akses_semua_cabang', 'modul' => 'role', 'title' => 'Akses Semua Cabang',
                'content' => 'Aktif = user dengan role ini bisa melihat data semua cabang. Nonaktif = hanya cabang yang terhubung ke user tersebut.'],
        ];
    }

    private function tooltipsTahapA(): array
    {
        return [
            ['key' => 'master_bahan_baku.harga_standar', 'modul' => 'master_bahan_baku', 'title' => 'Harga Standar',
                'content' => 'Harga patokan per satuan pakai (mis. per pcs). Diisi manual dan tidak berubah otomatis saat PO diterima. Dipakai sebagai harga awal PO dan estimasi HPP resep. HPP penjualan tetap FIFO.'],
            ['key' => 'master_bahan_baku.harga_po_terakhir', 'modul' => 'master_bahan_baku', 'title' => 'Harga PO Terakhir',
                'content' => 'Harga per satuan pakai dari PO terakhir yang diterima. Hanya informasi — tidak mengubah Harga Standar.'],
            ['key' => 'master_bahan_baku.harga_cabang', 'modul' => 'master_bahan_baku', 'title' => 'Harga Beli per Outlet',
                'content' => 'Isi kalau harga beli di outlet tertentu berbeda. Kosong = memakai Harga Standar. Dipakai sebagai harga awal saat outlet itu membuat PO.'],
        ];
    }
    // =====================================================================
    // Tahap F — Harga & stok per outlet di halaman tersendiri (1 outlet per tampilan)
    // =====================================================================

    private function sectionsTahapF(): array
    {
        return [
            'bahan-baku' => <<<'MD'
## 🔄 Update 2026-10-19 — Harga & Stok per Outlet
Supaya tidak bingung, harga **selalu menyebut lokasinya**.
- **Daftar Bahan Baku** menampilkan harga untuk outlet yang tertulis di kotak kuning di atas tabel (mengikuti **Cabang Aktif**). Owner/Admin Pusat bisa memilih outlet lain atau **Harga Umum** dari kotak itu.
- **Form Tambah/Edit** hanya berisi data yang berlaku untuk semua outlet: nama, satuan, unit beli, **Harga Standar**, dan **Qty Minimum Awal** (dipakai otomatis untuk outlet yang belum diatur sendiri).
- Harga beli & Qty Minimum yang **berbeda per outlet** diatur lewat tombol **Harga Outlet** (ikon toko) di daftar. Halaman itu hanya menampilkan **satu outlet**; ganti outlet lewat pilihan di bagian atas. Harga bisa diisi per pcs atau per pack — kolom lainnya terhitung otomatis. Kosong = memakai Harga Standar.
- Manajer cabang hanya bisa membuka outletnya sendiri.
MD,
            'produk-jual' => <<<'MD'
## 🔄 Update 2026-10-19 — Harga per Outlet di Halaman Tersendiri
- **Daftar Produk Jual** menampilkan harga di outlet yang tertulis di kotak kuning (mengikuti **Cabang Aktif**), sama dengan harga yang muncul di POS outlet itu. Ada tanda kalau produk **tidak dijual** di outlet tersebut. Pilih **Harga Umum** untuk melihat harga dasar beserta jumlah outlet yang harganya berbeda.
- **Harga Jual Umum** (form produk) = harga untuk semua outlet yang tidak punya harga sendiri.
- Saat **menambah** produk, cukup centang outlet tempat produk dijual. Harga khusus per outlet diatur setelah disimpan lewat tombol **Harga Outlet**.
- Halaman **Harga Outlet** (satu outlet per tampilan): Dijual di outlet ini, Harga Jual outlet, **HPP** (perkiraan dari resep memakai harga beli bahan di outlet itu), **Untung** dan **Margin** yang langsung terhitung saat harga diketik.
- HPP di halaman itu adalah perkiraan untuk menentukan harga. Modal riil tiap transaksi tetap dihitung FIFO dari stok outlet.
MD,
            'cabang' => <<<'MD'
## 🔄 Update 2026-10-19 — Salin Harga & Pengaturan Barang
Saat **menambah cabang**, pilih **"Salin Harga & Pengaturan Barang dari"** salah satu cabang yang sudah berjalan. Yang disalin: produk yang dijual/tidak, harga jual outlet, harga beli bahan outlet, dan Qty Minimum. **Stok tidak ikut disalin.** Setelah itu harga cabang baru bisa diubah sendiri lewat tombol Harga Outlet di menu Bahan Baku / Produk Jual.
MD,
            'stok-barang' => <<<'MD'
## 🔄 Update 2026-10-19 — Qty Minimum per Lokasi
Qty Minimum setiap baris stok adalah milik **lokasi itu** (ubah lewat tombol Set Minimum di sini, atau di Bahan Baku → tombol Harga Outlet). Barang yang minimumnya 0 tidak dihitung sebagai "stok rendah". Kartu Stok menampilkan stok & minimum lokasi yang dipilih.
MD,
            'laporan-stok' => <<<'MD'
## 🔄 Update 2026-10-19 — Stok Minimum per Lokasi
Laporan Stok Minimum hanya menampilkan barang yang Qty Minimum-nya **diatur (> 0)** di lokasi tersebut. Atur minimum per outlet di Bahan Baku → tombol Harga Outlet.
MD,
        ];
    }

    private function tooltipsTahapF(): array
    {
        return [
            ['key' => 'master_bahan_baku.pengaturan_outlet', 'modul' => 'master_bahan_baku', 'title' => 'Qty Minimum Outlet',
                'content' => 'Batas stok minimum di outlet ini. Kalau stok sama atau di bawah angka ini, muncul peringatan stok menipis. Kosong = memakai Qty Minimum Awal.'],
            ['key' => 'master_bahan_baku.qty_minimum_awal', 'modul' => 'master_bahan_baku', 'title' => 'Qty Minimum Awal',
                'content' => 'Dipakai otomatis untuk outlet yang belum diatur sendiri saat stoknya pertama kali tercatat. Minimum yang berbeda per outlet diatur lewat tombol Harga Outlet di daftar Bahan Baku.'],
            ['key' => 'master_produk_jual.hpp_outlet', 'modul' => 'master_produk_jual', 'title' => 'HPP & Untung di Outlet',
                'content' => 'Perkiraan HPP dari resep memakai harga beli bahan di outlet ini (atau Harga Standar kalau tidak diatur). Untung = Harga Jual − HPP. Modal riil transaksi tetap FIFO dari stok outlet.'],
            ['key' => 'master_produk_jual.harga_umum', 'modul' => 'master_produk_jual', 'title' => 'Harga Jual Umum',
                'content' => 'Harga untuk semua outlet yang tidak punya harga sendiri. Harga khusus per outlet diatur lewat tombol Harga Outlet di daftar Produk Jual.'],
            ['key' => 'cabang.salin_pengaturan', 'modul' => 'cabang', 'title' => 'Salin Harga & Pengaturan Barang',
                'content' => 'Cabang baru langsung memakai produk yang dijual, harga jual, harga beli bahan, dan Qty Minimum dari cabang yang dipilih. Stok tidak ikut disalin.'],
        ];
    }
}