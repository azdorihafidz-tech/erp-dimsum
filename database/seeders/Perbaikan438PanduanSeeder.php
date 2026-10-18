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
}
