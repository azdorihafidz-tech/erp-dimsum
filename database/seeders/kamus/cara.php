<?php

/**
 * 4.50 Kamus Menu — kelompok "Cara Melakukan": pertanyaan yang punya LEBIH DARI 1 cara.
 * Tiap entri: ada berapa cara, tabel perbandingan, pilih yang mana, wajib, jangan.
 */
return [

    [
        'slug' => 'cara-memindahkan-barang', 'judul' => 'Bagaimana memindahkan barang dari gudang ke cabang?', 'kelompok' => 'Cara Melakukan', 'urutan' => 10,
        'letak' => 'Stok & Gudang / Pembelian / POS Gudang',
        'ringkasan' => 'Ada 3 cara: Permintaan Stok → Transfer, PO ke Gudang, Kirim ke Cabang. Bedanya di harga, untung gudang, dan kas cabang.',
        'izin' => ['stok.request', 'stok.transfer', 'pembelian.view', 'kiriman.view'],
        'route_names' => [],
        'terkait' => ['transfer-stok', 'permintaan-stok', 'purchase-order', 'kiriman-cabang', 'supplier'],
        'kata_kunci' => 'pindah barang kirim barang gudang ke cabang distribusi kirim bahan antar cabang mutasi',
        'konten' => <<<'MD'
## Ada 3 cara

| | ① Permintaan Stok → Transfer | ② PO ke Gudang | ③ Kirim ke Cabang |
|---|---|---|---|
| Dimulai oleh | Cabang minta (atau gudang langsung Transfer) | Cabang membuat PO | Gudang, dari POS Gudang |
| Barang | Bahan Baku, Kemasan, Tambahan Gratis | Bahan, atau produk jual gudang | Produk Jual gudang |
| Harga | Modal (tanpa untung) | Harga di PO | Harga jual gudang |
| Untung gudang | ❌ | Hanya kalau dibayar di POS gudang | ✅ otomatis |
| Kas cabang berkurang | ❌ | Saat Catat Pembayaran PO | ✅ otomatis saat Terima |
| Masuk laporan penjualan gudang | ❌ | Kalau dibayar di POS gudang | ✅ |
| Stok gudang berkurang | Saat Kirim | Saat dibayar di POS gudang | Saat Kirim |
| Stok cabang bertambah | Saat Terima | Saat Terima PO | Saat Terima |
| Perlu diaktifkan | — | Supplier "Gudang" ditandai Supplier Internal | Saklar "Aktifkan Kirim ke Cabang" di Cabang → Edit (Gudang) |

## Pilih yang mana?
- Sekadar memindahkan bahan antar lokasi sendiri, tanpa jual-beli → **①**
- Gudang menjual ke cabang dan harus ada untung → **③**
- Cabang ingin memesan dulu, gudang dianggap supplier → **②**

## Wajib (semua cara)
- **Kode Item** barang di gudang dan di cabang **sama persis**. Barang dicocokkan dari kodenya; kalau beda, cabang mendapat barang baru dobel (Transfer/Kiriman) atau barang dilewati (Permintaan → Transfer).

## Jangan
- Jangan memakai 2 cara untuk barang yang sama (mis. Transfer lalu PO juga) — stok cabang jadi dobel.
- Jangan memakai PO ke supplier luar bernama "Gudang" (bukan Supplier Internal) — stok gudang tidak berkurang dan gudang tidak tercatat dapat pemasukan.
MD,
    ],

    [
        'slug' => 'cara-menambah-stok', 'judul' => 'Bagaimana menambah stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 20,
        'letak' => 'Pembelian / Stok & Gudang',
        'ringkasan' => 'Ada 5 cara: PO diterima, Penyesuaian Stok, Produksi, Transfer diterima, Kiriman diterima. Harga (modal) batchnya berbeda-beda.',
        'izin' => ['stok.view', 'pembelian.view', 'stok.adjustment', 'produksi.view', 'kiriman.view'],
        'route_names' => [],
        'terkait' => ['purchase-order', 'penyesuaian-stok', 'produksi', 'transfer-stok', 'kiriman-cabang'],
        'kata_kunci' => 'tambah stok isi stok stok masuk stok awal barang masuk restock',
        'konten' => <<<'MD'
## Ada 5 cara

| Cara | Kapan dipakai | Harga (modal) batch | Kas |
|---|---|---|---|
| **PO → Terima** | Beli dari supplier | Harga di PO | Berkurang saat Catat Pembayaran |
| **Penyesuaian Stok** (naik) | Stok awal, hasil opname lebih banyak | Harga diisi sendiri / Harga Standar | Tidak berubah |
| **Produksi** (gudang) | Membuat bahan setengah jadi | Biaya bahan ÷ hasil | Tidak berubah |
| **Transfer** diterima | Dikirim dari lokasi sendiri | Modal dari lokasi asal | Tidak berubah |
| **Kiriman** diterima | Dibeli dari gudang | Harga kirim gudang | Kas cabang berkurang |

## Pilih yang mana?
- Barang dibeli → **PO**. Ini satu-satunya cara yang mencatat harga beli & hutang/pembayaran supplier.
- Mulai pakai aplikasi (stok awal) → **Penyesuaian Stok** dengan harga diisi.

## Wajib
- Pastikan batch punya **harga > 0**. Batch Rp0 membuat modal (HPP) penjualan 0 dan laba terlihat terlalu besar.

## Jangan
- Jangan menambah stok barang yang dibeli lewat Penyesuaian — harga beli & pembayaran supplier tidak tercatat.
MD,
    ],

    [
        'slug' => 'cara-mengurangi-stok', 'judul' => 'Bagaimana mengurangi stok?', 'kelompok' => 'Cara Melakukan', 'urutan' => 30,
        'letak' => 'POS / Stok & Gudang',
        'ringkasan' => 'Ada 5 cara: dijual di POS, Pemakaian Perlengkapan, Penyesuaian Stok, Transfer/Kiriman keluar, Retur ke gudang. Pencatatan keuangannya beda.',
        'izin' => ['stok.view', 'order.create', 'pemakaian_perlengkapan.view', 'stok.adjustment'],
        'route_names' => [],
        'terkait' => ['pos', 'pemakaian-perlengkapan', 'penyesuaian-stok', 'transfer-stok', 'retur-cabang'],
        'kata_kunci' => 'kurangi stok stok keluar buang barang barang rusak barang hilang pakai barang',
        'konten' => <<<'MD'
## Ada 5 cara

| Cara | Untuk apa | Tercatat sebagai |
|---|---|---|
| **Dijual di POS** | Bahan komposisi produk | HPP penjualan |
| **Pemakaian Perlengkapan** | Gas, sabun, tisu, ATK (Jenis = Perlengkapan) | Laporan Pemakaian Perlengkapan |
| **Penyesuaian Stok** | Rusak, hilang, susut, salah hitung | Rusak/hilang/susut → Beban Kerugian Stok; salah hitung/audit → tidak dicatat ke keuangan |
| **Transfer / Kiriman** (Kirim) | Pindah ke lokasi lain | Pindah stok (Kiriman: penjualan gudang) |
| **Retur Cabang** | Kembalikan kiriman ke gudang | Pindah stok, uang bisa kembali |

Stok Barang juga punya **Reset ke 0**, tapi tidak mencatat alasan/beban — hindari.

## Perlu diketahui
- **Tambahan Gratis** (sumpit, saus sachet) hanya terpotong di POS kalau **Lacak Stok**-nya dicentang (Bahan Baku & Kemasan → Edit). Kalau tidak dicentang, kurangi berkala lewat Pemakaian Perlengkapan (Jenis = Perlengkapan) atau Penyesuaian Stok.

## Jangan
- Jangan membuang barang basi dengan Reset — pakai Penyesuaian alasan **Rusak/Kadaluarsa** supaya nilainya masuk beban.
MD,
    ],

    [
        'slug' => 'cara-membatalkan-transaksi', 'judul' => 'Bagaimana membatalkan atau memperbaiki transaksi penjualan?', 'kelompok' => 'Cara Melakukan', 'urutan' => 40,
        'letak' => 'Penjualan → Riwayat Order / POS',
        'ringkasan' => 'Ada 4 pilihan: Batalkan, Hapus, Edit, Batalkan Bill Tersimpan. Akibatnya ke stok & kas sangat berbeda.',
        'izin' => ['order.view', 'order.create'],
        'route_names' => [],
        'terkait' => ['riwayat-order', 'pos', 'closing'],
        'kata_kunci' => 'batal order batalkan transaksi salah input salah transaksi hapus order koreksi order refund cancel',
        'konten' => <<<'MD'
## Ada 4 pilihan

| Pilihan | Untuk order | Stok | Kas | Laporan |
|---|---|---|---|---|
| **Batalkan** | Sudah dibayar (Selesai) | ✅ Kembali | ✅ Uang dikeluarkan | Keluar dari laporan |
| **Hapus** | Hanya Pending / Dibatalkan | Tidak berubah | Tidak berubah | Disembunyikan |
| **Edit** | Pending: diskon & metode bayar. Selesai/Dibatalkan: hanya nama & catatan | — | Tidak berubah | Pending: total ikut diskon |
| **Batalkan Bill Tersimpan** | Bill Pending di POS | — | — | — |

## Pilih yang mana?
- Salah input order yang sudah dibayar → **Batalkan**, lalu buat order baru yang benar, lalu **Tandai Pengganti**.
- Order salah yang sudah dibatalkan dan ingin dibersihkan → **Hapus**.

## Syarat
- Selain Owner, Batalkan hanya untuk order **yang dibuat hari ini**.
- Tanggal yang sudah **Closing/Tutup Buku** terkunci — minta Owner Buka Kunci dulu.
- Kiriman ke cabang yang sudah diterima tidak bisa dibatalkan → **Retur Cabang**.

## Jangan
- Diskon/metode bayar order yang sudah dibayar dikunci — koreksinya selalu lewat Batalkan + order baru.
MD,
    ],

    [
        'slug' => 'cara-produk-di-banyak-cabang', 'judul' => 'Bagaimana membuat produk yang sama di banyak cabang?', 'kelompok' => 'Cara Melakukan', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Produk Jual / Pengaturan → Cabang & Gudang',
        'ringkasan' => 'Ada 4 cara: input manual per cabang, Ambil dari Cabang Lain, Buat juga di cabang, dan Salin saat Tambah Cabang.',
        'izin' => ['master.produk_jual.create', 'cabang.create'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'bahan-baku', 'cabang'],
        'kata_kunci' => 'salin produk copy produk produk semua cabang cabang baru duplikat produk ambil produk',
        'konten' => <<<'MD'
Data barang **terpisah per cabang**: setiap cabang punya bahan, produk, komposisi, dan harga sendiri. Supaya tidak input berulang, pakai salah satu cara di bawah.

## Ada 4 cara

| Cara | Kapan | Yang disalin |
|---|---|---|
| Input manual di tiap cabang | Produknya memang beda per cabang | — |
| **Ambil dari Cabang Lain** (Produk Jual) | Cabang sudah ada, ambil sebagian produk | Produk, foto, varian, komposisi, Bumbu Pusat; bahan dicocokkan, yang belum ada dibuat |
| **Buat juga di cabang** (form Tambah Produk Jual) | Membuat produk baru sekaligus untuk beberapa cabang | Sama seperti Ambil |
| **Salin Harga & Pengaturan Barang dari** (form Tambah Cabang) | Membuka cabang baru | Semua bahan, produk, komposisi, Bumbu Pusat, Qty Minimum |

Semua cara **tidak menyalin stok** dan **tidak sinkron otomatis** setelahnya — ubah harga di satu cabang tidak mengubah cabang lain.

## Wajib
- Periksa layar pemeriksaan bahan: bahan dengan **kode sama tapi nama beda** wajib dipilih manual.

## Jangan
- Jangan membuat bahan dengan kode berbeda untuk barang yang sama di cabang lain — Transfer/Kiriman tidak bisa mencocokkannya.
MD,
    ],

    [
        'slug' => 'cara-produk-tidak-muncul-di-pos', 'judul' => 'Kenapa produk tidak muncul / Habis di POS?', 'kelompok' => 'Cara Melakukan', 'urutan' => 60,
        'letak' => 'POS / Produk Jual / Bahan Baku',
        'ringkasan' => 'Daftar pemeriksaan: cabang aktif, status aktif, tipe, kategori, komposisi, stok bahan, kas default.',
        'izin' => ['order.create', 'master.produk_jual.view'],
        'route_names' => [],
        'terkait' => ['pos', 'produk-jual', 'bahan-baku', 'stok-barang'],
        'kata_kunci' => 'produk tidak muncul produk hilang dari pos stok habis di pos tidak bisa diklik menu tidak muncul kategori tidak muncul tidak bisa bayar',
        'konten' => <<<'MD'
## Produk tidak muncul sama sekali
1. **Cabang** di pojok kanan atas sudah benar? Produk hanya tampil di cabang pemiliknya.
2. Produk **Aktif**? (Produk Jual → Edit → Status Aktif)
3. **Tipe** Produk Jual atau Produk Tambahan? Bahan Baku/Kemasan tidak tampil di POS (kecuali Tambahan Gratis).
4. Sedang memilih **chip kategori** lain? Klik chip "Semua".
5. Di **Gudang Pusat** dengan mode Kirim ke Cabang — hanya produk milik gudang.

## Produk tampil tapi "Habis"
1. Produk punya **komposisi**? Tanpa komposisi produk memakai stoknya sendiri (biasanya 0).
   **Tambahan Gratis** dengan Lacak Stok dicentang → cek stoknya di cabang ini (isi lewat PO/Penyesuaian), atau hilangkan centang Lacak Stok.
2. Stok **setiap bahan** komposisi cukup di cabang ini? Lihat tulisan "Stok tersedia … (terbatas: nama bahan)" di POS, lalu cek Stok Barang.
3. Bahan komposisi **milik cabang yang sama**?
4. Satuan takaran benar? (gram vs kg)

## Tidak bisa bayar
- "Cabang belum punya Kas default …" → buat/aktifkan kas dengan Default untuk Tunai/Transfer/QRIS.
- Bukti Transfer/QRIS wajib → unggah foto, atau matikan "Wajib bukti" di Cabang → Edit.
- Tanggal sudah Closing → transaksi ditolak; minta Owner Buka Kunci.

## Kategori tidak muncul sebagai chip
- Kategori baru tampil kalau sudah punya minimal 1 produk aktif.
MD,
    ],

    [
        'slug' => 'cara-membuat-resep', 'judul' => 'Bagaimana membuat resep / komposisi?', 'kelompok' => 'Cara Melakukan', 'urutan' => 70,
        'letak' => 'Produk Jual / Master Bumbu Pusat / Produksi',
        'ringkasan' => 'Ada 3 tempat resep: komposisi Produk Jual, Master Bumbu Pusat, Resep Produksi. Bedanya kapan bahan dipotong.',
        'izin' => ['master.produk_jual.view', 'master.resep_bumbu.view', 'produksi.view'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'master-bumbu-pusat', 'produksi'],
        'kata_kunci' => 'resep komposisi takaran bahan per porsi hpp modal bumbu adonan',
        'konten' => <<<'MD'
## Ada 3 tempat resep

| | Komposisi Produk Jual | Master Bumbu Pusat | Resep Produksi |
|---|---|---|---|
| Untuk | 1 produk | Campuran yang dipakai banyak produk | Mengolah bahan jadi stok di gudang |
| Bahan dipotong | Saat produk dijual di POS | Saat produk yang menautkannya dijual | Saat klik Catat Produksi |
| Hasilnya | Modal (HPP) per order | Ikut ke HPP produk | Stok barang hasil bertambah |
| Ubah resep | Hanya produk itu | **Semua produk yang menautkan** langsung berubah | Produksi berikutnya |
| Lokasi | Cabang pemilik produk | Cabang pemilik bumbu | Hanya Gudang Pusat |

## Pilih yang mana?
- Bahan langsung dipakai saat menyajikan → **Komposisi Produk Jual**.
- Banyak produk memakai campuran yang sama → buat **Bumbu Pusat**, lalu Import ke tiap produk.
- Gudang memasak dulu dalam jumlah besar lalu dikirim → **Resep Produksi**; hasilnya (barang setengah jadi) dipakai di komposisi produk.

## Wajib
- Takaran per **1 porsi/1 produk**, dalam satuan yang benar (gram vs kg).
- Bahan milik cabang yang sama.
MD,
    ],

    [
        'slug' => 'cara-mengatur-harga', 'judul' => 'Bagaimana mengatur harga?', 'kelompok' => 'Cara Melakukan', 'urutan' => 80,
        'letak' => 'Bahan Baku / Produk Jual / Role & Hak Akses',
        'ringkasan' => 'Harga jual (Produk Jual, varian), Harga Standar bahan, harga PO, dan izin kasir mengubah harga di POS.',
        'izin' => ['master.produk_jual.view', 'master.bahan_baku.view'],
        'route_names' => [],
        'terkait' => ['produk-jual', 'bahan-baku', 'purchase-order', 'pos'],
        'kata_kunci' => 'harga harga jual harga beli harga per cabang harga standar harga varian ubah harga promo diskon',
        'konten' => <<<'MD'
## Jenis harga

| Harga | Diatur di | Dipakai untuk |
|---|---|---|
| **Harga Jual** | Produk Jual | Harga di POS cabang pemilik produk |
| **Harga Varian** | Produk Jual → Varian | Harga kombinasi varian (kosong = harga produk) |
| **Harga Standar** | Bahan Baku & Kemasan | Perkiraan modal saat stok habis & belum pernah PO; harga batch saat Penyesuaian naik; harga awal form PO. Tidak ditimpa PO |
| **Harga PO** | Form PO | Modal batch yang sebenarnya (FIFO) |
| Ubah harga di POS | Centangan **Ubah Harga Jual di POS** | Tanpa centangan, kasir tidak bisa mengubah harga |

## Harga beda per cabang
Data barang terpisah per cabang, jadi **harga diatur di produk/bahan milik setiap cabang** (pilih cabang di pojok kanan atas, lalu edit).

## Jangan
- Untuk potongan/promo, pakai kolom **Diskon** di POS, bukan mengubah harga.
- Jangan mengira Harga Standar = modal sebenarnya: modal penjualan selalu dari batch FIFO (harga PO).
MD,
    ],

    [
        'slug' => 'cara-mengirim-uang-ke-pusat', 'judul' => 'Bagaimana memindahkan uang (cabang ke pusat, antar kas)?', 'kelompok' => 'Cara Melakukan', 'urutan' => 90,
        'letak' => 'Keuangan',
        'ringkasan' => 'Ada 3 cara: Setoran Kasir, Transfer Dana, Transfer Antar Kas. Bedanya di tujuan, persetujuan, dan pengecekan selisih.',
        'izin' => ['setoran_kasir.view', 'setoran.view', 'transfer_antar_kas.view'],
        'route_names' => [],
        'terkait' => ['setoran-kasir', 'transfer-dana', 'transfer-antar-kas', 'kas-transaksi'],
        'kata_kunci' => 'setor uang kirim uang ke pusat pindah uang uang kasir setoran harian transfer bank',
        'konten' => <<<'MD'
## Ada 3 cara

| | Setoran Kasir | Transfer Dana | Transfer Antar Kas |
|---|---|---|---|
| Untuk | Uang tunai penjualan harian kasir | Uang bebas antar cabang | Antar kas di cabang yang sama |
| Jumlah | Dihitung sistem dari POS, kasir isi uang nyata | Diisi sendiri | Diisi sendiri |
| Cek selisih | ✅ | ❌ | ❌ |
| Persetujuan | Pusat Approve/Reject | Penerima Terima/Tolak | Tidak perlu |
| Kas asal berkurang | Saat **Approve** | Saat **Kirim** | Saat Simpan |
| Kas tujuan bertambah | Saat Approve | Saat Terima | Saat Simpan |
| Butuh Gudang Pusat | ✅ (kas tunai pusat) | ❌ | ❌ |

## Pilih yang mana?
- Kasir menyerahkan uang penjualan hari itu ke pusat → **Setoran Kasir**.
- Uang lain dikirim ke cabang lain (modal, pinjaman) → **Transfer Dana**.
- Uang laci disetor ke rekening bank cabang yang sama → **Transfer Antar Kas**.
- Usaha 1 cabang tanpa Gudang Pusat → **Transfer Antar Kas**.

## Jangan
- Jangan memakai 2 cara untuk uang yang sama (mis. Setoran Kasir lalu Transfer Dana lagi) — kas pusat jadi dobel.
MD,
    ],

    [
        'slug' => 'cara-mencatat-uang', 'judul' => 'Bagaimana mencatat uang masuk/keluar?', 'kelompok' => 'Cara Melakukan', 'urutan' => 100,
        'letak' => 'Keuangan / POS / Pembelian',
        'ringkasan' => 'Sebagian otomatis (POS, PO, setoran), sebagian manual (Kas & Transaksi, Transaksi Berulang). Ini daftarnya.',
        'izin' => ['keuangan.view'],
        'route_names' => [],
        'terkait' => ['kas-transaksi', 'transaksi-berulang', 'purchase-order', 'pos'],
        'kata_kunci' => 'catat pengeluaran catat pemasukan biaya operasional bayar listrik bayar gaji bayar supplier uang keluar uang masuk',
        'konten' => <<<'MD'
## Yang tercatat otomatis (jangan dicatat lagi manual)
| Kejadian | Dicatat oleh |
|---|---|
| Penjualan ke pelanggan | POS (saat order dibayar) |
| Bayar supplier | Purchase Order → Catat Pembayaran |
| Setor/kirim uang | Setoran Kasir / Transfer Dana / Transfer Antar Kas |
| Penjualan gudang ke cabang | Kiriman Cabang (saat cabang Terima) |
| Barang rusak/hilang | Penyesuaian Stok (beban tanpa kas) |

## Yang dicatat manual
| Cara | Untuk |
|---|---|
| **Kas & Transaksi → Tambah Transaksi** | Biaya sekali-sekali (listrik, perbaikan, belanja kecil), pemasukan lain |
| **Transaksi Berulang** | Biaya rutin bulanan yang sama (sewa, langganan) |

## Wajib
- Pilih **kas** yang benar dan **kategori** yang tepat — kategori menentukan baris di laporan & BEP.

## Jangan
- Jangan mencatat pembelian bahan dari supplier lewat Tambah Transaksi saja — buat **PO** supaya stok & modal (HPP) ikut tercatat; bayarnya lewat "Catat Pembayaran" di PO.
MD,
    ],

    [
        'slug' => 'cara-melihat-untung', 'judul' => 'Bagaimana melihat untung?', 'kelompok' => 'Cara Melakukan', 'urutan' => 110,
        'letak' => 'Penjualan / Laporan / Keuangan',
        'ringkasan' => 'Ada 5 laporan yang menampilkan "untung" dengan hitungan berbeda. Ini bedanya dan kapan dipakai.',
        'izin' => ['penjualan.laporan.keuntungan', 'laporan.laba_rugi.view', 'laporan.laba_rugi_formal.view', 'closing.view', 'laporan.keuangan.view'],
        'route_names' => [],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-laba-rugi', 'laporan-laba-rugi-formal', 'closing', 'laporan-keuangan'],
        'kata_kunci' => 'untung laba keuntungan rugi profit margin laba bersih laba kotor',
        'konten' => <<<'MD'
## Ada 5 laporan

| Laporan | Untung = | Sudah dikurangi biaya operasional? | Bisa berubah? |
|---|---|---|---|
| **Penjualan → Laporan Transaksi/Produk/Kategori** | Pendapatan (setelah diskon) − HPP bahan | ❌ | Ya (berjalan) |
| **Laporan → Laba Rugi** | Omzet baris order (sebelum diskon order) − HPP bahan | ❌ | Ya |
| **Laporan → Laba Rugi Formal** | Pendapatan − HPP − beban per akun COA | ✅ | Ya |
| **Closing / Tutup Buku** | Omzet bersih − HPP − beban (tanpa transfer internal) | ✅ | **Tidak** — dibekukan saat closing |
| Laporan → Keuangan / Keuangan → Laporan Keuangan | Uang masuk − uang keluar usaha (tanpa transfer internal & saldo awal), **berbasis kas** | ✅ (yang sudah dicatat di kas) | Ya — pembelian bahan dihitung saat dibayar, bukan saat dipakai (HPP) |

## Pilih yang mana?
- Produk mana yang paling menguntungkan → **Laporan Produk** (keuntungan) atau **Laba Rugi per produk**.
- Untung bersih bulan ini → **Laba Rugi Formal**, lalu kunci dengan **Tutup Buku Bulanan**.
- Angka resmi yang tidak boleh berubah lagi → **Closing**.

## Wajib (supaya untung akurat)
- Setiap bahan punya **harga batch > 0** (PO dengan harga benar / stok awal dengan harga).
- Biaya operasional (sewa, listrik, pembelian aset) **dicatat di Kas & Transaksi** dengan kategori yang tepat. Gaji tercatat otomatis saat slip di-**Bayar** di menu Penggajian.
MD,
    ],
];
