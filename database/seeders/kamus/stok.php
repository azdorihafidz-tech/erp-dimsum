<?php

/**
 * 4.50 Kamus Menu — kelompok Stok & Gudang.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    [
        'slug' => 'dashboard-stok', 'judul' => 'Dashboard Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 10,
        'letak' => 'Stok & Gudang → Dashboard Stok',
        'ringkasan' => 'Ringkasan kondisi stok: barang hampir habis, nilai stok per lokasi, barang jarang bergerak, umur stok, tren harga.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.dashboard', 'stok.dashboard.*', 'stok.aging', 'stok.top-movement', 'stok.trend-harga', 'stok.stok-mati'],
        'terkait' => ['stok-barang', 'bahan-baku', 'laporan-stok'],
        'kata_kunci' => 'dashboard stok stok menipis stok habis nilai stok persediaan slow moving stok mati aging umur stok tren harga',
        'konten' => <<<'MD'
## Fungsi
Melihat kondisi stok dengan cepat sebelum belanja atau memeriksa gudang.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Data mengikuti cabang di pojok kanan atas.
- Yang dihitung hanya **Bahan Baku, Kemasan, Tambahan Gratis**. Produk Jual tidak punya stok sendiri (stoknya dihitung dari bahan), jadi tidak tampil.
- Peringatan "hampir habis" hanya untuk barang yang **Qty Minimum-nya diisi** (lebih dari 0) di Stok Barang.

## Yang dihitung
- **Nilai stok** = harga FIFO setiap batch yang tersisa (sama dengan Neraca/Closing). Barang tanpa batch memakai Harga Standar.
- **Stok mati / jarang bergerak** = barang yang lama tidak keluar.

## Hasil
Hanya menampilkan data. Tidak mengubah stok.
MD,
    ],

    [
        'slug' => 'stok-barang', 'judul' => 'Stok Barang', 'kelompok' => 'Stok & Gudang', 'urutan' => 20,
        'letak' => 'Stok & Gudang → Stok Barang',
        'ringkasan' => 'Jumlah stok setiap barang per lokasi, kartu stok (riwayat keluar-masuk), atur Qty Minimum, dan reset stok.',
        'izin' => ['stok.view'],
        'route_names' => ['stok.index', 'stok.kartu'],
        'terkait' => ['penyesuaian-stok', 'dashboard-stok', 'bahan-baku', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'stok barang jumlah stok sisa stok kartu stok riwayat stok qty minimum batas minimum reset stok',
        'konten' => <<<'MD'
## Fungsi
Melihat sisa stok setiap barang di setiap lokasi, dan riwayat keluar-masuknya (**Kartu Stok**).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Stok**. Tombol lain punya centangan sendiri:
  - **Set Threshold Stok Minimum per Lokasi** — mengisi Qty Minimum.
  - **Hapus/Reset Stok ke 0 (Destruktif)** — tombol Reset.
  - **Penyesuaian Stok** — tombol Penyesuaian (lihat Kamus "Penyesuaian Stok").
- Yang tampil hanya Bahan Baku, Kemasan, Tambahan Gratis milik cabang aktif.
- Barang baru muncul di sini setelah **pertama kali punya stok** (lewat PO diterima, Penyesuaian, Transfer/Kiriman diterima, atau Produksi).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Kartu Stok | Hanya melihat riwayat |
| Set Qty Minimum | Menentukan batas "hampir habis" di lokasi ini → peringatan di Dashboard Stok, Dashboard Owner, Laporan Stok Minimum |
| **Reset ke 0** | Wajib ketik nama barang. Semua batch barang itu di lokasi ini dihapus, stok jadi 0, tercatat di kartu stok. **Tidak** mencatat rugi di keuangan |

## Jangan
- Jangan pakai Reset untuk stok opname — pakai **Penyesuaian Stok** supaya selisihnya tercatat dengan alasan (rusak/hilang/salah hitung).
MD,
    ],

    [
        'slug' => 'penyesuaian-stok', 'judul' => 'Penyesuaian Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 25,
        'letak' => 'Stok & Gudang → Stok Barang → tombol Penyesuaian Stok',
        'ringkasan' => 'Menyamakan stok di sistem dengan hasil hitung fisik. Selisihnya dicatat dengan alasan; rusak/hilang dicatat sebagai beban.',
        'izin' => ['stok.adjustment'],
        'route_names' => ['stok.adjustment', 'stok.adjustment.*'],
        'terkait' => ['stok-opname', 'stok-barang', 'pemakaian-perlengkapan', 'cara-menambah-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'penyesuaian stok adjustment stock opname opname hitung fisik selisih stok rusak hilang susut kadaluarsa koreksi stok stok awal',
        'konten' => <<<'MD'
## Fungsi
Mengisi **jumlah stok fisik** hasil hitung, lalu sistem menghitung selisihnya dengan stok di sistem dan mencatatnya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Penyesuaian Stok**.
- Pilih cabang di pojok kanan atas. Barang yang bisa dipilih: semua barang aktif **milik cabang ini**.
- Kalau barang punya **Unit Beli** (pack/dus), jumlah fisik bisa diisi per pack — otomatis diubah ke satuan pakai.

## Alasan & akibatnya ke keuangan
| Alasan | Stok | Catatan keuangan |
|---|---|---|
| Susut, Rusak/Kadaluarsa, Hilang/Dicuri | Naik/turun sesuai selisih | Selisih turun → **Beban Kerugian Stok**; selisih naik → **Koreksi Stok Masuk**. Tanpa kas (saldo kas tidak berubah), tapi masuk Laporan Keuangan/Laba Rugi |
| Salah Hitung, Audit (Stock Opname) | Naik/turun | Selisih **turun** → **Selisih Persediaan** (tanpa kas, masuk Laba Rugi). Selisih **naik** tidak dicatat ke keuangan |
| Lainnya | Naik/turun | Hanya dicatat kalau "Catat sebagai biaya rugi" dicentang |

- **Stok turun** — batch tertua dipotong dulu (FIFO), atau pilih batch sendiri.
- **Stok naik** — dibuat batch baru dengan harga: harga yang diisi sendiri, atau **Harga Standar** barang di cabang ini. Bisa juga menambah ke batch yang sudah ada.
- Owner/Admin Pusat dapat notifikasi; stok minimum dicek ulang.

- Barang yang sedang ada di **Stok Opname** yang belum selesai di lokasi itu **tidak bisa** disesuaikan di sini (juga Hapus Stok) — selesaikan/batalkan opname itu dulu.

## Wajib
- Pilih alasan yang benar — alasan menentukan apakah selisih jadi beban/pemasukan di laporan.
- Untuk **stok awal** saat mulai pakai aplikasi: Penyesuaian dengan harga diisi (atau isi Harga Standar dulu), supaya modal (HPP) penjualan tidak 0.

## Jangan
- Untuk hitung fisik rutin (akhir bulan) pakai menu **Stok Opname**: ada hitung buta, rincian penyebab, foto, persetujuan, dan berita acara.
- Jangan pakai Penyesuaian untuk barang yang dipakai sehari-hari (gas, plastik, sabun) — pakai **Pemakaian Perlengkapan** supaya ada catatan pemakaiannya.
- Jangan pakai Penyesuaian untuk memindahkan barang ke cabang lain — pakai **Transfer Stok**.
MD,
    ],

    [
        'slug' => 'stok-opname', 'judul' => 'Stok Opname', 'kelompok' => 'Stok & Gudang', 'urutan' => 26,
        'letak' => 'Stok & Gudang → Stok Opname',
        'ringkasan' => 'Hitung fisik stok per lokasi dengan hitung buta, rincian penyebab selisih, foto, dan persetujuan. Kerugian masuk Laba Rugi tanpa mengurangi kas.',
        'izin' => ['stok_opname.view', 'stok_opname.hitung', 'stok_opname.ajukan', 'stok_opname.setujui'],
        'route_names' => ['stok-opname.*'],
        'terkait' => ['penyesuaian-stok', 'stok-barang', 'produksi', 'cara-mengurangi-stok'],
        'kata_kunci' => 'stok opname stock opname hitung fisik hitung stok cek stok selisih stok barang hilang barang rusak basi kedaluwarsa freezer mati susut sampel tester makan karyawan salah catat lebih berita acara akhir bulan audit stok',
        'konten' => <<<'MD'
## Fungsi
Mencocokkan stok sistem dengan hitungan fisik di satu lokasi. Setiap selisih wajib dirinci penyebabnya, lalu disetujui. Saat disetujui stok disesuaikan dan kerugiannya dicatat sebagai **beban tanpa kas** — muncul di Laba Rugi, saldo kas tidak berubah.

## Supaya menu ini muncul & bisa dipakai
- Menu muncul kalau punya salah satu centangan: **Lihat Stok Opname**, **Hitung Stok Opname**, **Mulai & Periksa Stok Opname**, atau **Setujui / Tolak Stok Opname**.
- **Mulai** opname & isi rincian: centangan **Mulai & Periksa Stok Opname**. **Mengisi jumlah**: centangan **Hitung Stok Opname** dan dipilih sebagai penghitung (pemeriksa juga boleh menghitung).
- **Setujui/Tolak**: centangan **Setujui / Tolak Stok Opname** (bawaan: Owner & Admin Pusat). Orang yang ikut menghitung tidak bisa menyetujui opname itu, kecuali Owner.
- **Batalkan**: centangan **Batalkan Stok Opname**.
- Hanya lokasi yang menjadi akses user. Barang yang bisa dihitung: Bahan Baku, Kemasan, Tambahan Gratis **milik lokasi itu** yang dicentang *Lacak Stok* (Produk Jual tidak punya stok sendiri).
- **Hitung buta** & **batas nilai wajib foto barang hilang** diatur di **Pengaturan Umum**.

## Alur
Mulai → **Sedang dihitung** (isi jumlah / scan kode) → pemeriksa cek selisih, minta hitung ulang, rincikan penyebab → **Ajukan** → **Menunggu persetujuan** → **Setujui** (selesai, dikunci) atau **Tolak** (kembali dihitung). **Batalkan** bisa sebelum disetujui.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Keuangan | Kas |
|---|---|---|---|
| Mulai Opname | Belum berubah. Barang yang dipilih terkunci dari Penyesuaian Stok & Hapus Stok | — | — |
| Isi jumlah / scan | Belum berubah. Stok sistem saat itu dicatat per barang | — | — |
| Ajukan / Tolak / Batalkan | Belum berubah | — | — |
| **Setujui** | Disesuaikan sebesar **selisih** (fisik − sistem saat dihitung), bukan menimpa angka. Kurang → batch tertua dipotong (FIFO). Rusak/Basi/Freezer mati → masuk **Stok Rusak**. Lebih → batch baru harga pokok rata-rata | 1 baris per penyebab, tanpa kas: Kerugian Stok – Rusak / Basi / Hilang, Penyusutan Stok, Promosi & Sampel, Makan Karyawan (Beban Konsumsi), Selisih Persediaan; kelebihan → Selisih Lebih Persediaan (pendapatan lain) | Tidak berubah |
| **Berita Acara** (PDF/Excel) | Tidak berubah | — | — |


- Kartu stok mencatat tiap penyesuaian sebagai **Penyesuaian** dengan nomor SO yang bisa diklik.
- Berita Acara sebelum disetujui bertanda **SEMENTARA**; penghitung yang hitung buta baru bisa mengunduhnya setelah opname selesai.
- Rangkuman kerugian per periode: **Laporan → Kerugian Stok**.
- Baris keuangan opname tidak bisa diedit/dihapus dari Kas & Transaksi.

## Wajib
- Rincian penyebab barang yang kurang harus **sama dengan selisih** (boleh dipecah).
- **Foto** untuk Rusak, Basi/Kedaluwarsa, Freezer mati; untuk Hilang kalau nilainya mencapai batas di Pengaturan Umum.
- Semua barang dalam opname harus dihitung (atau dikeluarkan dengan alasan) sebelum diajukan.

## Jangan
- Jangan menyetujui kalau hari itu sudah **Closing Harian / Tutup Buku** — akan ditolak; buka kunci atau setujui besok.
- Jangan mencatat waste saat produksi lewat opname — itu dicatat di hasil nyata menu **Produksi**.
- Kalau stok keburu terjual sehingga tidak cukup dikurangi saat disetujui, persetujuan ditolak: tolak opname dan hitung ulang barang itu.

## Menu yang mirip & bedanya
- **Penyesuaian Stok**: koreksi cepat satu barang, langsung berlaku tanpa persetujuan & tanpa rincian penyebab. Stok Opname untuk hitung fisik rutin yang perlu bukti & persetujuan.
- **Pemakaian Perlengkapan**: mencatat barang habis pakai sehari-hari, bukan selisih hitung.
MD,
    ],

    [
        'slug' => 'stok-rusak', 'judul' => 'Stok Rusak', 'kelompok' => 'Stok & Gudang', 'urutan' => 27,
        'letak' => 'Stok & Gudang → Stok Rusak',
        'ringkasan' => 'Barang rusak/basi/freezer mati dari Stok Opname ditahan di sini. Tindak lanjut: kembali ke pemasok (ganti barang / uang / potong tagihan), musnahkan, atau dipakai lagi.',
        'izin' => ['stok_opname.stok_rusak', 'stok_opname.view'],
        'route_names' => ['stok-rusak.*'],
        'terkait' => ['stok-opname', 'laporan-kerugian-stok', 'supplier'],
        'kata_kunci' => 'stok rusak barang rusak barang basi kedaluwarsa freezer mati buang barang musnahkan berita acara pemusnahan retur ke pemasok supplier ganti barang potong tagihan dipakai lagi',
        'konten' => <<<'MD'
## Fungsi
Menahan barang yang dicatat Rusak, Basi/Kedaluwarsa, atau Freezer mati saat **Stok Opname disetujui**, lalu mencatat tindak lanjutnya. Barang di sini tidak termasuk stok jual (tidak bisa dijual, dipakai resep, atau ditransfer). Kerugiannya sudah dicatat saat opname disetujui.

## Supaya menu ini muncul & bisa dipakai
- Menu muncul dengan centangan **Kelola Stok Rusak** atau **Lihat Stok Opname**; tombol **Tindak lanjut** hanya dengan **Kelola Stok Rusak**.
- Isinya baru ada setelah ada Stok Opname yang **disetujui** dengan penyebab Rusak/Basi/Freezer mati.
- Hanya lokasi yang menjadi akses user. Untuk uang dari pemasok, lokasi harus punya **Kas** aktif.

## Apa yang terjadi kalau tombol diklik
| Tindakan | Stok | Keuangan | Kas |
|---|---|---|---|
| Diganti barang (pemasok) | Masuk lagi ke stok jual, harga pokok semula | Kerugian dibatalkan (baris minus di akun beban penyebabnya) | — |
| Dibayar uang (pemasok) | — | Pemulihan Kerugian Stok (pendapatan lain) | Kas terpilih **bertambah** |
| Dipotong tagihan (pemasok) | — | Pemulihan Kerugian Stok, nomor PO wajib | Kas yang membayar PO **bertambah** |
| Dimusnahkan | — | Tidak ada (sudah dicatat saat opname) | — |
| Dipakai lagi (hanya penyebab Rusak) | Masuk lagi ke stok jual, harga pokok semula | Kerugian dibatalkan | — |

## Wajib
- Foto untuk pemusnahan. Berita Acara Pemusnahan (PDF) dibuat otomatis dengan nomor `BA-TahunBulan-Urut`.
- Nomor PO / faktur untuk potong tagihan.

## Jangan
- Jangan memakai lagi barang basi, kedaluwarsa, atau kena freezer mati — sistem menolaknya.
- Jangan buang barang rusak lewat Penyesuaian Stok kalau sudah tercatat di sini — kerugiannya akan dobel.

## Menu yang mirip & bedanya
- **Retur Cabang**: mengembalikan kiriman dari cabang ke gudang (antar lokasi sendiri), bukan ke pemasok.
MD,
    ],

    [
        'slug' => 'permintaan-stok', 'judul' => 'Permintaan Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 30,
        'letak' => 'Stok & Gudang → Permintaan Stok',
        'ringkasan' => 'Cabang meminta bahan ke gudang/cabang lain. Setelah disetujui, gudang memprosesnya lewat Transfer Stok (tanpa jual-beli).',
        'izin' => ['stok.request'],
        'route_names' => ['stock-request.*'],
        'terkait' => ['transfer-stok', 'kiriman-cabang', 'purchase-order', 'cara-memindahkan-barang'],
        'kata_kunci' => 'permintaan stok request stok minta barang minta bahan cabang minta ke gudang',
        'konten' => <<<'MD'
## Fungsi
Cabang mengajukan daftar bahan yang dibutuhkan. Gudang menyetujui jumlahnya, lalu mengirim lewat **Transfer Stok**.

## Supaya menu ini muncul & bisa dipakai
- Membuat permintaan: centangan **Buat Permintaan Stok** + **Lihat Stok** (daftar permintaan memakai Lihat Stok).
- Menyetujui / menolak: centangan **Transfer Stok**.
- Pilih **cabang peminta** di pojok kanan atas. Barang yang bisa diminta: Bahan Baku, Kemasan, Tambahan Gratis **milik cabang peminta**.

## Alur
Pending → **Disetujui** (jumlah boleh diubah) / **Ditolak** → gudang klik "Buat Transfer" → Transfer dikirim → cabang Terima → permintaan **Diterima**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas |
|---|---|---|
| Buat / Setujui / Tolak / Batalkan permintaan | Belum berubah | — |
| Buat Transfer (dari permintaan) | Dibuat Transfer berstatus Draft | — |
| Selanjutnya | Lihat Kamus **Transfer Stok** | Tidak ada uang |

## Wajib
- **Kode Item barang di cabang dan di gudang harus sama persis.** Saat "Buat Transfer", barang yang tidak ditemukan padanannya di gudang **dilewati tanpa pesan**.

## Menu yang mirip & bedanya
- **Purchase Order ke Gudang** — cabang memesan ke supplier internal "Gudang", ada harga & bisa dibayar.
- **Kiriman Cabang** — gudang menjual produk ke cabang (ada untung gudang, kas cabang berkurang).
MD,
    ],

    [
        'slug' => 'transfer-stok', 'judul' => 'Transfer Stok', 'kelompok' => 'Stok & Gudang', 'urutan' => 40,
        'letak' => 'Stok & Gudang → Transfer Stok',
        'ringkasan' => 'Memindahkan bahan antar lokasi sendiri tanpa jual-beli: stok asal berkurang saat Kirim, stok tujuan bertambah saat Terima.',
        'izin' => ['stok.transfer'],
        'route_names' => ['stock-transfer.*'],
        'terkait' => ['permintaan-stok', 'kiriman-cabang', 'stok-barang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'transfer stok pindah barang pindah bahan mutasi stok kirim bahan gudang ke cabang antar cabang',
        'konten' => <<<'MD'
## Fungsi
Memindahkan bahan dari satu lokasi ke lokasi lain milik usaha sendiri. **Tidak ada uang yang bergerak** dan gudang tidak mendapat untung.

## Supaya menu ini muncul & bisa dipakai
- Membuat, mengirim: centangan **Transfer Stok** + **Lihat Stok**.
- Menerima di lokasi tujuan / membatalkan: centangan **Transfer Stok** atau **Buat Permintaan Stok**, dan user boleh mengakses lokasi itu.
- Barang yang bisa dipindah: **Bahan Baku, Kemasan, Tambahan Gratis** milik lokasi asal. Produk Jual tidak bisa (pakai Kiriman Cabang).

## Alur
Draft → **Dikirim** → **Diterima** / Diterima Sebagian, atau Dibatalkan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok asal | Stok tujuan | Kas |
|---|---|---|---|
| Simpan (Draft) | — | — | — |
| **Kirim** | Berkurang (FIFO) | — | — |
| **Terima** | — | Bertambah; modal = modal rata-rata yang keluar dari asal | — |
| Terima Sebagian | — | Bertambah sesuai jumlah diterima | — |
| **Batalkan** (Draft/Dikirim) | Kalau sudah Dikirim: kembali dengan modal yang sama | — | — |

Semua tercatat di Kartu Stok kedua lokasi. Tidak masuk laporan penjualan.

## Wajib
- **Kode Item di lokasi asal dan tujuan harus sama persis.** Saat Terima, barang dicari di lokasi tujuan berdasarkan Kode Item; kalau tidak ada, dibuat barang baru otomatis (bisa jadi barang dobel kalau kodenya beda sedikit).

## Jangan
- Jangan memindahkan barang yang sama lewat Transfer **dan** PO/Kiriman — stok tujuan jadi dobel.

## Menu yang mirip & bedanya
- **Kiriman Cabang** — gudang **menjual** produk jual ke cabang: kas cabang berkurang, gudang untung.
- **Permintaan Stok** — cara cabang meminta; prosesnya tetap lewat Transfer ini.
MD,
    ],

    [
        'slug' => 'produksi', 'judul' => 'Produksi', 'kelompok' => 'Stok & Gudang', 'urutan' => 50,
        'letak' => 'Stok & Gudang → Produksi',
        'ringkasan' => 'Gudang mengolah bahan mentah jadi bahan setengah jadi (mis. adonan, isian) berdasarkan Resep Produksi.',
        'izin' => ['produksi.view'],
        'route_names' => ['produksi.*'],
        'terkait' => ['bahan-baku', 'master-bumbu-pusat', 'kiriman-cabang', 'cara-membuat-resep'],
        'kata_kunci' => 'produksi masak olah bahan setengah jadi adonan isian batch produksi resep produksi hasil produksi',
        'konten' => <<<'MD'
## Fungsi
Mencatat pengolahan di gudang: bahan mentah berkurang, barang hasil bertambah dengan modal dari harga bahannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Produksi**; mencatat perlu **Catat Produksi**; membatalkan perlu **Batalkan Produksi**; membuat/mengubah resep perlu **Kelola Resep Produksi**.
- Hanya di cabang bertipe **Gudang Pusat** — pilih gudang di pojok kanan atas.
- **Barang hasil dan semua bahan** harus dibuat dulu di **Bahan Baku & Kemasan** milik gudang ini.
- Buat **Resep Produksi** dulu (tab Resep Produksi): 1 barang hasil = 1 resep, tidak boleh memakai dirinya sendiri sebagai bahan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas |
|---|---|---|
| **Catat Produksi** | Bahan = takaran resep × jumlah rencana, dipotong FIFO. Barang hasil bertambah sesuai **hasil nyata** | — |
| Modal hasil | Otomatis = total biaya bahan ÷ hasil nyata (atau diisi manual) | — |
| **Batalkan** | Bahan kembali, hasil keluar — hanya kalau stok hasil di gudang masih cukup | — |

- Kalau stok satu bahan kurang, produksi ditolak dan pesan menyebut semua bahan yang kurang.
- Harga Standar barang hasil hanya diisi kalau masih kosong (tidak ditimpa).

## Wajib
- Isi **hasil nyata** sesuai yang benar-benar jadi — modal per unit dihitung dari angka ini.

## Jangan
- Jangan batalkan produksi yang hasilnya sudah dipakai/dikirim — akan ditolak; koreksi lewat Penyesuaian Stok.

## Menu yang mirip & bedanya
- **Komposisi Produk Jual / Bumbu Pusat** — bahan dipotong **saat produk dijual** di POS. Produksi = bahan dipotong **saat diolah** di gudang, hasilnya jadi stok.
- **Kelola Antrian** — antrian pesanan pelanggan, bukan produksi stok.
MD,
    ],

    [
        'slug' => 'kiriman-cabang', 'judul' => 'Kiriman Cabang (Kirim ke Cabang)', 'kelompok' => 'Stok & Gudang', 'urutan' => 60,
        'letak' => 'Stok & Gudang → Kiriman Cabang (dikirim dari POS Gudang Pusat)',
        'ringkasan' => 'Gudang menjual produk ke cabang lewat POS. Cabang menerima: stok cabang bertambah, kas cabang berkurang, gudang dapat pendapatan.',
        'izin' => ['kiriman.view'],
        'route_names' => ['kiriman.*'],
        'terkait' => ['pos', 'retur-cabang', 'transfer-stok', 'cabang', 'cara-memindahkan-barang'],
        'kata_kunci' => 'kiriman cabang kirim ke cabang jual ke cabang gudang jual terima kiriman tolak kiriman internal untung gudang',
        'konten' => <<<'MD'
## Fungsi
Gudang menjual produk ke cabang. Cabang membayar dengan harga jual gudang, jadi gudang mendapat untung dan modal cabang = harga kirim.

## Supaya menu ini muncul & bisa dipakai
- **Saklar wajib dinyalakan**: Pengaturan → Cabang & Gudang → Edit (Gudang Pusat) → Config POS → **"Aktifkan Kirim ke Cabang di POS"**. Bawaannya mati — selama mati, kotak "Jenis Pembeli" tidak muncul di POS gudang.
- Gudang Pusat wajib punya **Kas Tunai aktif** (tempat pendapatan kiriman).
- Mengirim: centangan **Buat Order** (POS gudang). Melihat menu: **Lihat Kiriman Cabang**. Menerima/menolak di cabang: **Terima / Tolak Kiriman dari Gudang**. Membatalkan dari gudang: **Batalkan Order**.
- Barang yang dikirim = **Produk Jual milik gudang** yang tampil di POS gudang. Pesanan dari "Pesanan PO dari Cabang" tidak boleh dikirim lewat cara ini.

## Alur
POS gudang → Jenis Pembeli **Kirim ke Cabang** → pilih Cabang Tujuan → **Kirim** → di cabang: **Terima** (boleh sebagian) atau **Tolak**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok gudang | Stok cabang | Kas | Laporan |
|---|---|---|---|---|
| **Kirim** (POS gudang) | Bahan produk dipotong | — | — | Belum dihitung (status Dikirim) |
| **Terima** | Sisa yang tidak diterima kembali | Bertambah; modal = harga kirim setelah diskon | Kas cabang (dipilih, bawaan Tunai) **berkurang**; Kas Tunai gudang **bertambah** | Jadi penjualan gudang pada **tanggal Terima** |
| **Tolak** / Batalkan | Semua kembali | — | — | Tidak dihitung |

- Yang masuk ke cabang adalah **bahan yang dipotong di gudang** (isi komposisi produk), dicocokkan ke barang cabang berdasarkan **Kode Item**. Barang yang belum ada di cabang dibuat otomatis — daftarnya ditampilkan di halaman Terima.
- Kas cabang boleh menjadi minus.
- Di laporan "Semua Cabang" (Laporan Transaksi/Produk/Kategori, Tutup Buku Keseluruhan) kiriman dikeluarkan supaya omzet tidak dobel.

## Wajib
- Kode Item bahan di gudang dan di cabang **sama persis**.

## Jangan
- Kiriman yang sudah diterima **tidak bisa dibatalkan/diedit** — kalau barang basi/rusak/kelebihan, pakai **Retur Cabang**.

## Menu yang mirip & bedanya
- **Transfer Stok** — pindah bahan tanpa uang & tanpa untung.
- **PO ke Gudang** — cabang yang memesan; dibayar lewat POS gudang "Pesanan PO dari Cabang".
MD,
    ],

    [
        'slug' => 'retur-cabang', 'judul' => 'Retur Cabang', 'kelompok' => 'Stok & Gudang', 'urutan' => 70,
        'letak' => 'Stok & Gudang → Retur Cabang (dibuat dari kiriman yang sudah diterima)',
        'ringkasan' => 'Cabang mengembalikan barang kiriman gudang (basi, rusak, kelebihan). Uangnya bisa dikembalikan atau tidak.',
        'izin' => ['retur.view'],
        'route_names' => ['retur-cabang.*'],
        'terkait' => ['kiriman-cabang', 'riwayat-order'],
        'kata_kunci' => 'retur cabang kembalikan barang barang basi barang rusak kelebihan kirim refund kiriman',
        'konten' => <<<'MD'
## Fungsi
Mengembalikan sebagian/seluruh barang dari **kiriman gudang yang sudah diterima**.

## Supaya menu ini muncul & bisa dipakai
- Membuat/membatalkan retur (cabang): **Buat / Batalkan Retur ke Gudang**. Menerima/menolak (gudang): **Terima / Tolak Retur dari Cabang**. Melihat: **Lihat Retur Cabang**.
- Retur hanya bisa dari kiriman berstatus **diterima**, maksimal sejumlah yang diterima dan belum pernah diretur.
- Untuk retur dengan uang kembali: Gudang wajib punya **Kas Tunai aktif** dan cabang punya kas aktif.

## Alur
Cabang buat retur (pilih alasan: Basi / Rusak / Kelebihan kirim / Lainnya, dan **uang kembali Ya/Tidak**) → status Menunggu → gudang **Terima** atau **Tolak**.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok cabang | Stok gudang | Kas |
|---|---|---|---|
| **Kirim Retur** (cabang) | Berkurang | — | — |
| **Terima**, uang kembali **Ya** | — | Bertambah dengan modal asli gudang | Kas gudang berkurang → **kas cabang yang dulu membayar** bertambah. Ada order koreksi minus di gudang |
| **Terima**, uang kembali **Tidak** | — | Bertambah dengan modal Rp0 (rugi ditanggung cabang) | — |
| **Tolak** / Batalkan | Kembali | — | — |

## Jangan
- Order koreksi retur di gudang tidak bisa dibatalkan, diedit, atau dihapus.
- Barang basi yang sudah diterima gudang tetap jadi stok gudang — buang lewat **Penyesuaian Stok** alasan Rusak/Kadaluarsa.
MD,
    ],

    [
        'slug' => 'bahan-baku', 'judul' => 'Bahan Baku & Kemasan', 'kelompok' => 'Stok & Gudang', 'urutan' => 80,
        'letak' => 'Stok & Gudang → Bahan Baku & Kemasan',
        'ringkasan' => 'Data semua barang yang dibeli dan disimpan: bahan baku, kemasan, tambahan gratis, perlengkapan. Termasuk Harga Standar dan unit beli.',
        'izin' => ['master.bahan_baku.view'],
        'route_names' => ['master.bahan-baku.*'],
        'terkait' => ['produk-jual', 'stok-barang', 'purchase-order', 'pemakaian-perlengkapan', 'cara-mengatur-harga'],
        'kata_kunci' => 'bahan baku kemasan perlengkapan tambahan gratis sumpit plastik master barang harga standar harga beli unit beli pack dus kode item satuan kategori',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan barang yang dibeli dan disimpan stoknya. Barang di sini dipakai di PO, komposisi Produk Jual, Bumbu Pusat, Produksi, Transfer, dan stok opname.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Bahan Baku & Kemasan**.
- Barang **milik cabang** yang dipilih di pojok kanan atas. Saat "Semua Cabang", pilih pemiliknya di form (bawaan Gudang Pusat).
- **Kode Item unik per cabang** — kode yang sama di cabang lain dianggap barang yang sama saat Transfer/Kiriman/Ambil Produk.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Tipe: Bahan Baku / Kemasan | Bisa dibeli, distok, dipakai di komposisi |
| Tipe: **Tambahan Gratis** | Juga tampil di POS bagian "Item Tambahan" (harga Rp0). Kalau **Lacak Stok** dicentang: stok cabang dipotong saat dibayar | Tipe: **Tambahan Gratis** | Juga tampil di POS bagian "Item Tambahan" (harga Rp0). ⚠️ Stoknya **tidak berkurang** saat dipilih di POS — kurangi lewat Pemakaian Perlengkapan atau Penyesuaian Stok | bertanda "Habis" kalau kosong. Tidak dicentang: stoknya tidak dihitung |
| Jenis: Bahan Baku / **Perlengkapan** | Perlengkapan (gas, sabun, ATK) bisa dicatat di menu Pemakaian Perlengkapan |
| Lacak Stok | Tidak dicentang: barang tidak bisa dipilih di Pemakaian Perlengkapan, dan (Tambahan Gratis) tidak dipotong di POS |
| **Satuan** | Satuan stok & satuan harga — harga selalu per satuan ini |
| **Unit Beli** + isi (mis. pack = 100 pcs) | Di PO & Penyesuaian bisa isi per pack, otomatis diubah ke satuan |
| **Harga Standar** | Diisi manual. Dipakai saat stok habis & belum pernah PO, saat Penyesuaian stok naik, dan harga awal di form PO. **PO tidak menimpanya** (kecuali masih kosong) |
| Kategori (+) | Tombol + membuat kategori baru |

Edit barang menampilkan **FIFO Batch** (batch mana yang dipakai berikutnya) dan harga PO terakhir.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Simpan | Barang siap dipakai; stoknya 0 sampai ada PO/Penyesuaian/Transfer |
| **Hapus** | Hanya untuk barang yang **belum dipakai**: ditolak kalau masih ada stok, dipakai di komposisi produk / Bumbu Pusat / Resep Produksi, atau ada di PO yang belum selesai (pesan menyebut alasannya). Barang masuk Data Terhapus (bisa dipulihkan); riwayat order | **Hapus** | Barang + stoknya + baris PO & order yang memakainya disembunyikan (Data Terhapus, bisa dipulihkan) | PO lama tetap utuh |

## Wajib
- Satuan harga = satuan stok. Contoh: stok dalam gram → harga per gram. Kalau stok kg tapi harga per gram, HPP jadi 1.000× lipat.

## Jangan
- Barang yang sudah tidak dipakai lagi cukup **dinonaktifkan** (hilangkan centang Aktif) — riwayatnya tetap aman.
- Jangan membuat barang jadi (mis. minuman kaleng) sebagai Produk Jual saja — buat di sini sebagai Bahan Baku, lalu buat Produk Jual dengan komposisi 1 barang itu.

## Menu yang mirip & bedanya
- **Produk Jual** — yang dijual di POS (punya harga jual & komposisi). Bahan Baku = yang dibeli & disimpan.
- **Semua Barang (Overview)** — hanya melihat semua barang sekaligus.
MD,
    ],

    [
        'slug' => 'produk-jual', 'judul' => 'Produk Jual', 'kelompok' => 'Stok & Gudang', 'urutan' => 90,
        'letak' => 'Stok & Gudang → Produk Jual',
        'ringkasan' => 'Produk yang dijual di POS: harga jual, foto, kategori, varian, dan komposisi bahan yang dipotong saat terjual.',
        'izin' => ['master.produk_jual.view'],
        'route_names' => ['master.produk-jual.*'],
        'terkait' => ['pos', 'bahan-baku', 'master-bumbu-pusat', 'cara-produk-di-banyak-cabang', 'cara-produk-tidak-muncul-di-pos', 'cara-membuat-resep', 'cara-mengatur-harga'],
        'kata_kunci' => 'produk jual menu produk harga jual resep komposisi varian ukuran level rasa foto produk kategori produk ambil dari cabang lain buat juga di cabang import bumbu hpp modal',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan produk yang dijual di POS. Produk jual **tidak punya stok sendiri** — saat terjual, bahan di **komposisinya** yang dipotong.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Produk Jual**.
- Produk **milik cabang** di pojok kanan atas; muncul di POS cabang itu saja.
- Bahan di komposisi harus sudah ada di **Bahan Baku & Kemasan milik cabang yang sama**.

## Isian penting & akibatnya
| Isian | Akibat |
|---|---|
| Tipe: **Produk Jual** | Tampil di grid utama POS |
| Tipe: **Produk Tambahan** | Tampil di bagian "Item Tambahan" (berbayar). Sama seperti Produk Jual: komposisinya dipotong saat terjual; tanpa komposisi memakai stoknya sendiri |
| Harga Jual | Harga di POS. Kasir hanya bisa mengubahnya kalau punya centangan Ubah Harga |
| Status Aktif | Tidak aktif = hilang dari POS |
| Kategori | Chip filter di POS & Laporan Kategori. Kategori tampil di POS kalau punya ≥1 produk aktif |
| Jenis Menu (opsional) | Kelompok untuk Laporan Laba Rugi "Per Jenis Menu" (daftarnya di Penjualan → Jenis Menu). Namanya ikut tersimpan di order saat dijual |
| **Komposisi** | Bahan + takaran per 1 produk. Menentukan stok yang dipotong, tanda "Habis", dan HPP |
| Import dari Bumbu Pusat | Menautkan Bumbu Pusat (ubah bumbu → semua produk ikut) |
| **Varian** (Ukuran/Level/Rasa) | Kombinasi varian bisa punya harga sendiri. Komposisi & stok tetap mengikuti produk induk |
| Foto | Tampil di grid POS |

Kolom **Harga Bahan** & **Total HPP** di komposisi = perkiraan dari harga FIFO bahan (batch tertua yang masih ada; habis → harga PO terakhir; belum pernah PO → Harga Standar).

## Tombol khusus
| Tombol | Akibat |
|---|---|
| **Ambil dari Cabang Lain** | Menyalin produk (beserta foto, varian, komposisi, Bumbu Pusat) ke cabang lain. Bahan dicocokkan per Kode + Nama; yang belum ada dibuat (stok 0). Tanpa sinkron setelahnya |
| **Buat juga di cabang** (form Tambah) | Setelah simpan, produk langsung disalin ke cabang yang dicentang lewat layar pemeriksaan yang sama |
| Hapus | Produk masuk Data Terhapus (bisa dipulihkan lengkap dengan foto). Riwayat order tetap utuh. Ditolak kalau produk masih ada di PO yang belum selesai |

## Wajib
- Setiap produk jual **harus punya komposisi**. Tanpa komposisi, produk selalu "Habis" di POS.

## Jangan
- Produk yang berhenti dijual cukup **dinonaktifkan** (hilang dari POS, bisa diaktifkan lagi kapan saja).
- Jangan menaruh barang yang dibeli jadi langsung sebagai Produk Jual tanpa bahan (lihat Kamus Bahan Baku).

## Menu yang mirip & bedanya
- **Master Bumbu Pusat** — resep bersama yang ditautkan ke banyak produk.
- **Bahan Baku & Kemasan** — barang yang dibeli & distok.
MD,
    ],

    [
        'slug' => 'semua-barang', 'judul' => 'Semua Barang (Overview)', 'kelompok' => 'Stok & Gudang', 'urutan' => 100,
        'letak' => 'Stok & Gudang → Semua Barang (Overview)',
        'ringkasan' => 'Daftar semua barang (bahan & produk) dalam satu tabel. Hanya untuk melihat — tambah/ubah lewat Bahan Baku atau Produk Jual.',
        'izin' => ['item.view'],
        'route_names' => ['item.*'],
        'terkait' => ['bahan-baku', 'produk-jual'],
        'kata_kunci' => 'semua barang master barang lengkap overview daftar barang item',
        'konten' => <<<'MD'
## Fungsi
Melihat semua barang sekaligus (bahan, kemasan, tambahan, produk jual).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Master Barang**. Data mengikuti cabang di pojok kanan atas.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| + Bahan/Kemasan | Membuka form Bahan Baku & Kemasan |
| + Produk Jual | Membuka form Produk Jual |
| Edit | Diarahkan ke form Bahan Baku atau Produk Jual sesuai tipenya |

Tidak ada tombol hapus di sini. Centangan "Tambah/Edit Master Barang" sudah tidak dipakai — yang berlaku centangan di Bahan Baku & Produk Jual.
MD,
    ],

    [
        'slug' => 'pemakaian-perlengkapan', 'judul' => 'Pemakaian Perlengkapan', 'kelompok' => 'Stok & Gudang', 'urutan' => 110,
        'letak' => 'Stok & Gudang → Pemakaian Perlengkapan',
        'ringkasan' => 'Mencatat pemakaian barang habis pakai (gas, sabun, plastik, ATK) supaya stoknya berkurang dan nilainya tercatat.',
        'izin' => ['pemakaian_perlengkapan.view'],
        'route_names' => ['pemakaian-perlengkapan.*'],
        'terkait' => ['bahan-baku', 'penyesuaian-stok', 'cara-mengurangi-stok'],
        'kata_kunci' => 'pemakaian perlengkapan barang habis pakai gas sabun tisu plastik atk pakai barang',
        'konten' => <<<'MD'
## Fungsi
Mencatat barang yang dipakai untuk operasional (bukan dijual), misalnya gas, sabun, tisu.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Pemakaian Perlengkapan**; mencatat perlu **Catat Pemakaian Perlengkapan**.
- Barang yang bisa dipilih hanya yang di **Bahan Baku & Kemasan** diberi **Jenis = Perlengkapan** dan **Lacak Stok** dicentang, milik cabang aktif.

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Laporan |
|---|---|---|---|
| **Simpan** | Berkurang (FIFO); nilai (modal) tercatat | — | Laporan Pemakaian Perlengkapan |

Kalau stok kurang, ditolak.

## Jangan
- **Tidak ada tombol batal/hapus.** Kalau salah catat, koreksi lewat **Penyesuaian Stok** (alasan Salah Hitung).

## Menu yang mirip & bedanya
- **Penyesuaian Stok** — untuk stok opname/rusak/hilang, bukan pemakaian rutin.
MD,
    ],
];
