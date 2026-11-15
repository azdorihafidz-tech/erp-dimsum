<?php

/**
 * 4.50 Kamus Menu — kelompok Penjualan & Antrian Produksi.
 * Setiap fakta di sini diambil dari kode (controller/service/view), bukan ingatan.
 * Kalau perilaku menu berubah, UBAH JUGA teks di sini (aturan CLAUDE.md 3.6 poin 6).
 */
return [

    [
        'slug' => 'pos', 'judul' => 'POS / Kasir', 'kelompok' => 'Penjualan', 'urutan' => 10,
        'letak' => 'Penjualan → POS / Kasir',
        'ringkasan' => 'Mencatat penjualan ke pelanggan: pilih produk, terima pembayaran, cetak struk. Stok bahan langsung terpotong dan kas bertambah.',
        'izin' => ['order.create'],
        'route_names' => ['penjualan.pos'],
        'terkait' => ['riwayat-order', 'produk-jual', 'bahan-baku', 'cabang', 'kas-transaksi', 'setoran-kasir', 'cara-produk-tidak-muncul-di-pos'],
        'kata_kunci' => 'kasir jualan jual transaksi bayar struk order pesanan checkout tunai qris transfer split bill simpan bill bill tersimpan uang pas kembalian diskon laci printer meja dine in takeaway frozen',
        'konten' => <<<'MD'
## Fungsi
Tempat kasir mencatat penjualan ke pelanggan. Begitu **PROSES ORDER** diklik, stok bahan di cabang ini terpotong dan uang masuk ke kas cabang.

## Supaya menu ini muncul & bisa dipakai
- Pilih **cabang** di pojok kanan atas. Kalau sedang "Semua Cabang", POS meminta memilih cabang dulu (Gudang Pusat juga bisa dipilih).
- Cabang wajib punya **kas aktif dengan "Default untuk" Tunai, Transfer, dan QRIS** (Keuangan → Kas & Transaksi → Kelola Kas). Kalau salah satu tidak ada, saat Bayar muncul pesan *"Cabang belum punya Kas default untuk pembayaran …"*.
- **Produk tampil** kalau dibuat di **Produk Jual** milik cabang ini dan statusnya Aktif. Produk Jual tampil di grid utama; Produk Tambahan dan Tambahan Gratis (dari Bahan Baku & Kemasan) tampil di bagian "Item Tambahan".
- **Produk tidak bertanda "Habis"** kalau stok semua bahan di komposisinya cukup di cabang ini (termasuk bahan di dalam Bumbu Pusat). Produk Jual & Produk Tambahan **tanpa komposisi** memakai stoknya sendiri — produk jual tidak pernah dibeli, jadi biasanya selalu Habis. Karena itu setiap produk jual harus punya komposisi.
- **Tambahan Gratis** (sumpit, saus sachet): kalau **Lacak Stok** dicentang, stoknya dipotong saat dibayar dan bertanda "Habis" kalau stok cabang kosong; kalau tidak dicentang, tidak dihitung stoknya.
- **Chip kategori** di atas grid hanya muncul untuk kategori yang punya minimal 1 produk aktif.

### Pengaturan per cabang yang mengubah POS
Diatur di **Pengaturan → Cabang & Gudang → Edit**:

| Pengaturan | Akibat di POS |
|---|---|
| Dine-in / Takeaway / Frozen aktif | Tombol tipe transaksi yang tampil |
| Tampilkan pilihan Tipe Transaksi + Tipe bawaan | Kalau dimatikan, kasir tidak memilih tipe — otomatis memakai tipe bawaan. Kalau hanya 1 tipe aktif, tipe itu dipilih otomatis |
| Nomor meja aktif | Kolom nomor meja saat Dine-in |
| Take Away Fee | Biaya tambahan otomatis saat Takeaway |
| Service Charge % | Ditambahkan ke total |
| Wajib bukti Transfer / QRIS | Foto bukti wajib diunggah sebelum bayar |
| Izinkan sembunyi harga per item di struk | Muncul centang "Tampilkan harga per item di struk" |
| Footer Struk | Teks di bagian bawah struk |
| Sistem Antrian Produksi | Order Dine-in/Takeaway dapat nomor antrian (Frozen tidak) |
| Label Produk (stiker) | Tombol cetak label + kolom catatan per item |
| Aktifkan Kirim ke Cabang (hanya Gudang Pusat) | Muncul kotak "Jenis Pembeli: Kirim ke Cabang" |

### Centangan izin tambahan (Role & Hak Akses)
| Centangan | Tanpa centangan ini |
|---|---|
| Ubah Harga Jual di POS (kolom Harga/Unit) | Kolom harga terkunci (ikon gembok), harga selalu harga produk di cabang ini |
| Batalkan Bill Tersimpan (POS) | Tombol Batalkan di daftar Bill Tersimpan tidak ada |
| Buka Laci Kasir Manual | Tombol Buka Laci tidak ada |
| Lihat Antrian Produksi | Tombol Cek Antrian tidak ada |
| Cetak Label Produk (stiker) | Tidak bisa mencetak label |

## Alur
Klik produk (pilih varian kalau ada) → atur jumlah/diskon → pilih tipe transaksi → pilih pelanggan (boleh kosong) → pilih metode bayar (boleh dibagi/split) → **PROSES ORDER** → struk tampil.

Klik produk + varian yang sama dua kali → jumlahnya bertambah di baris yang sama (kecuali baris itu sudah diberi catatan).

## Apa yang terjadi kalau tombol diklik
| Tombol | Stok | Kas | Laporan |
|---|---|---|---|
| **PROSES ORDER** | Bahan komposisi dipotong (FIFO, batch tertua dulu); produk tanpa komposisi & Tambahan Gratis ber-Lacak Stok memotong stoknya sendiri. Modal (HPP) tercatat permanen di order | Kas "default" metode bayar bertambah sebesar **nilai order** (kembalian tidak ikut) | Masuk semua laporan penjualan, Laba Rugi, Setoran Kasir (bagian tunai) |
| **Save Bill** | Belum dipotong | Belum | Belum dihitung (status Pending) |
| Bayar bill tersimpan | Dipotong saat dibayar | Bertambah | Masuk laporan |
| Batalkan bill tersimpan | — | — | Bill berstatus Dibatalkan |
| Print Bill | — | — | Hanya pratinjau tagihan |
| Buka Laci | — | — | Tercatat di log |
| **Kirim ke Cabang** (POS gudang) | Stok gudang dipotong, menunggu diterima cabang | Belum (uang pindah saat cabang klik Terima) | Belum dihitung sampai diterima |

Kalau stok bahan kurang, order **ditolak** dan pesan menyebut bahan yang kurang.

## Wajib
- Bahan di komposisi produk harus **milik cabang yang sama** dengan produknya.
- Satuan harga bahan = satuan stoknya (lihat Bahan Baku & Kemasan).

## Jangan
- **Jangan Closing Harian sebelum toko tutup.** Setelah closing, tanggal itu terkunci: transaksi baru di hari itu ditolak.
- Jangan memakai Save Bill sebagai catatan utang lama — bill Pending tidak masuk laporan dan stoknya belum terpotong.
- Jangan memberi potongan dengan mengubah kolom Harga — pakai kolom **Diskon** supaya tercatat sebagai diskon.

## Menu yang mirip & bedanya
- **Riwayat Order** — melihat, membatalkan, dan mencetak ulang order yang sudah dibuat. POS hanya untuk membuat order baru.
- **Cek Antrian** — mencari order berdasarkan nomor antrian dan menandai sudah diambil.
MD,
    ],

    [
        'slug' => 'riwayat-order', 'judul' => 'Riwayat Order', 'kelompok' => 'Penjualan', 'urutan' => 20,
        'letak' => 'Penjualan → Riwayat Order',
        'ringkasan' => 'Daftar semua order cabang aktif: lihat detail, cetak ulang struk/label, batalkan, kembalikan, edit, atau hapus order.',
        'izin' => ['order.view'],
        'route_names' => ['penjualan.index', 'penjualan.show', 'penjualan.edit'],
        'terkait' => ['pos', 'kiriman-cabang', 'retur-cabang', 'closing', 'cara-membatalkan-transaksi'],
        'kata_kunci' => 'riwayat order histori transaksi batal batalkan cancel hapus order kembalikan edit order cetak ulang struk order pengganti salah input refund',
        'konten' => <<<'MD'
## Fungsi
Melihat order yang sudah dibuat di cabang aktif, lalu memperbaikinya kalau ada yang salah.

## Supaya menu ini muncul & bisa dipakai
- Data yang tampil mengikuti **cabang di pojok kanan atas**. "Semua Cabang" hanya untuk role yang boleh melihat semua cabang.
- Setiap tombol punya centangan sendiri di Role & Hak Akses: **Batalkan Order**, **Kembalikan Order Dibatalkan**, **Edit Order**, **Hapus Order**. Tombol tidak tampil kalau centangannya tidak ada.

## Status order
**Pending** (bill tersimpan, belum dibayar) → **Selesai** (sudah dibayar) → bisa jadi **Dibatalkan**. Kiriman ke cabang punya status sendiri: Dikirim (menunggu diterima) → Diterima / Ditolak.

## Apa yang terjadi kalau tombol diklik
| Tombol | Syarat | Stok | Kas | Laporan |
|---|---|---|---|---|
| **Batalkan** | Wajib pilih alasan. Selain Owner, hanya order **yang dibuat hari ini** | Bahan yang dulu terpotong **kembali** | Uang order **dikeluarkan** dari kas | Order keluar dari semua laporan |
| **Kembalikan** | Order berstatus Dibatalkan | Dipotong lagi (dicek cukup dulu) | Tercatat lagi | Masuk laporan lagi |
| **Tandai Pengganti** | Order lama sudah Dibatalkan, di cabang sama, belum punya pengganti | — | — | Hanya menautkan order baru sebagai pengganti order lama |
| **Edit** | Selain Owner, hanya order Pending. Order Selesai/Dibatalkan: hanya nama/telepon & catatan — **diskon & metode bayar dikunci** | — | Tidak berubah | Pending: diskon (service charge ikut menyesuaikan) & metode bayar bisa diubah |
| **Hapus** | Hanya order **Pending atau Dibatalkan** | Tidak berubah | Tidak berubah | Order disembunyikan (masuk Data Terhapus) |
| Cetak ulang struk / label | — | — | — | — |

Kiriman ke cabang yang **belum diterima** bisa dibatalkan (stok kembali ke gudang). Yang **sudah diterima** tidak bisa dibatalkan — pakai **Retur Cabang**. Order koreksi dari retur tidak bisa dibatalkan/diedit.

## Wajib
- Order yang sudah dibayar harus **Batalkan dulu**, baru boleh dihapus. Tombol Hapus pada order Selesai ditolak.

## Jangan
- Diskon/metode bayar salah pada order yang sudah dibayar: jangan dicari cara mengubahnya — **Batalkan**, buat order baru yang benar, lalu **Tandai Pengganti**. Uangnya sudah tercatat di kas & Setoran Kasir, jadi aplikasi sengaja menguncinya.
- Tanggal yang sudah **Closing/Tutup Buku** terkunci: order di tanggal itu tidak bisa dibatalkan/diedit sampai Owner membuka kuncinya.

## Menu yang mirip & bedanya
- **POS** — membuat order baru.
- **Kiriman Cabang** — order kiriman gudang ke cabang (Terima/Tolak dilakukan di sana).
- **Data Terhapus** — tempat order yang dihapus; bisa dipulihkan.
MD,
    ],

    [
        'slug' => 'laporan-transaksi-kasir', 'judul' => 'Laporan Transaksi', 'kelompok' => 'Penjualan', 'urutan' => 30,
        'letak' => 'Penjualan → Laporan Transaksi',
        'ringkasan' => 'Pendapatan dan jumlah transaksi per tahun → bulan → hari → per transaksi, bisa dibuka kasir.',
        'izin' => ['penjualan.laporan_transaksi.view'],
        'route_names' => ['penjualan.laporan.transaksi'],
        'terkait' => ['laporan-produk-kasir', 'laporan-kategori-kasir', 'laporan-penjualan', 'riwayat-order'],
        'kata_kunci' => 'laporan transaksi omzet pendapatan harian bulanan tahunan grafik jam penjualan hari ini untung keuntungan kasir',
        'konten' => <<<'MD'
## Fungsi
Melihat pendapatan dan jumlah transaksi secara bertingkat: **Selama ini → Tahun → Bulan → Hari → daftar transaksi → struk**. Bisa juga memilih rentang tanggal sendiri.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Transaksi (menu Penjualan)**.
- Kolom/grafik **Keuntungan** hanya tampil kalau role juga punya centangan **Lihat Keuntungan di Laporan Penjualan**. Tanpa itu, angka keuntungan memang tidak dikirim ke layar (bukan sekadar disembunyikan).
- Data mengikuti cabang di pojok kanan atas. Role yang hanya boleh cabangnya sendiri selalu melihat cabangnya.

## Yang dihitung
- **Pendapatan** = total bayar order (setelah diskon, termasuk service charge & take away fee).
- **Keuntungan** = pendapatan − modal (HPP) barang yang terjual.
- Yang **tidak dihitung**: bill Pending, order Dibatalkan, order yang dihapus, kiriman ke cabang yang belum diterima. Di "Semua Cabang", kiriman gudang → cabang tidak dihitung (supaya tidak dobel).

## Hasil
Hanya menampilkan data — tidak mengubah stok, kas, atau order. Bisa diekspor ke Excel/PDF.

## Menu yang mirip & bedanya
- **Laporan Produk / Kategori** — pendapatan dipecah per produk / per kategori. Jumlah seluruh produk + biaya tambahan = pendapatan di laporan ini.
- **Laporan → Penjualan** (menu Laporan) — laporan lama yang lebih rinci untuk admin.
MD,
    ],

    [
        'slug' => 'laporan-produk-kasir', 'judul' => 'Laporan Produk', 'kelompok' => 'Penjualan', 'urutan' => 40,
        'letak' => 'Penjualan → Laporan Produk',
        'ringkasan' => 'Jumlah terjual, penjualan, diskon, dan pendapatan per produk (dan per varian) dalam periode tertentu.',
        'izin' => ['penjualan.laporan_produk.view'],
        'route_names' => ['penjualan.laporan.produk'],
        'terkait' => ['laporan-transaksi-kasir', 'laporan-kategori-kasir', 'produk-jual'],
        'kata_kunci' => 'laporan produk terlaris best seller produk laku penjualan per produk varian qty terjual',
        'konten' => <<<'MD'
## Fungsi
Melihat produk mana yang paling banyak terjual dan menghasilkan berapa, per periode (Hari ini / Bulan ini / Tahun ini / Selama ini / rentang tanggal).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Penjualan Produk (menu Penjualan)**. Keuntungan perlu centangan **Lihat Keuntungan di Laporan Penjualan**.

## Cara menghitung
- **Penjualan** = harga × jumlah di order.
- **Diskon** = diskon order dibagi ke setiap produk sesuai porsi harganya.
- **Pendapatan** = penjualan − diskon.
- Service charge & take away fee ditampilkan di baris "Biaya Tambahan" sendiri.
- Produk dikelompokkan per **Kode Item** — produk yang sama di beberapa cabang dijumlah jadi satu kalau kodenya sama.
- Varian dirinci kalau produk itu terjual dalam lebih dari 1 varian.

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel/PDF.

## Menu yang mirip & bedanya
- **Laporan Kategori** — sama, tapi dijumlah per kategori produk.
- **Laporan Transaksi** — per waktu (hari/bulan), bukan per produk.
MD,
    ],

    [
        'slug' => 'laporan-kategori-kasir', 'judul' => 'Laporan Kategori', 'kelompok' => 'Penjualan', 'urutan' => 50,
        'letak' => 'Penjualan → Laporan Kategori',
        'ringkasan' => 'Penjualan, diskon, dan pendapatan dijumlah per kategori produk, bisa dibuka rincian produknya.',
        'izin' => ['penjualan.laporan_kategori.view'],
        'route_names' => ['penjualan.laporan.kategori'],
        'terkait' => ['laporan-produk-kasir', 'laporan-transaksi-kasir'],
        'kata_kunci' => 'laporan kategori penjualan per kategori food drink frozen',
        'konten' => <<<'MD'
## Fungsi
Melihat kategori produk mana yang paling besar pendapatannya.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Laporan Penjualan Kategori (menu Penjualan)**. Keuntungan perlu centangan **Lihat Keuntungan di Laporan Penjualan**.

## Cara menghitung
Sama persis dengan Laporan Produk, lalu dijumlah per kategori — jadi angkanya selalu cocok dengan Laporan Produk. Produk tanpa kategori masuk "Tanpa kategori".

⚠️ Kategori diambil dari kategori produk jual. Kalau produk jual diberi kategori "Bahan Baku"/"Kemasan", kategori itu ikut muncul di sini — perbaiki kategorinya di Produk Jual.

## Hasil
Hanya menampilkan data. Bisa diekspor ke Excel/PDF.
MD,
    ],

    [
        'slug' => 'pelanggan', 'judul' => 'Pelanggan', 'kelompok' => 'Penjualan', 'urutan' => 60,
        'letak' => 'Penjualan → Pelanggan',
        'ringkasan' => 'Data pelanggan tetap (nama, telepon, alamat) yang bisa dipilih di POS, dipakai untuk riwayat belanja dan Program Loyalty.',
        'izin' => ['pelanggan.view'],
        'route_names' => ['pelanggan.*'],
        'terkait' => ['pos', 'program-loyalty'],
        'kata_kunci' => 'pelanggan customer member langganan data pembeli telepon riwayat belanja total pembelian',
        'konten' => <<<'MD'
## Fungsi
Menyimpan data pelanggan tetap supaya bisa dipilih di POS. Riwayat belanja dan progres Program Loyalty dihitung dari order yang memakai pelanggan ini.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Pelanggan**; tombol Tambah/Edit perlu centangan **Tambah/Edit Pelanggan**.
- Data pelanggan **sama untuk semua cabang** (pelanggan dari cabang mana pun bisa dipilih di POS cabang lain).
- Hanya pelanggan **Aktif** yang muncul di pilihan pelanggan POS.

## Detail pelanggan
- **Total Pembelian** = jumlah order **Selesai** sebelum hari ini (order hari ini dan order dibatalkan tidak dihitung).
- Daftar order dan progres Program Loyalty pelanggan ini.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah / Edit | Hanya data pelanggan. Order lama tidak berubah |
| Saklar **Aktif** dimatikan (Edit) | Tidak muncul lagi di pilihan POS. Riwayat belanja, laporan, progres loyalty tetap |

Menu ini tidak punya tombol Hapus — pakai saklar Aktif. Kalaupun data pelanggan dihapus, order-nya tetap tercatat dan tetap dihitung di laporan.

## Jangan
- Pelanggan yang sudah tidak dipakai cukup **dinonaktifkan**, jangan dibuat data baru yang dobel.

## Menu yang mirip & bedanya
- **Program Loyalty** — memakai data order pelanggan untuk menghitung hadiah.
- Di POS, kolom **Nama** boleh diisi tanpa mendaftarkan pelanggan (walk-in) — tapi order itu tidak tercatat ke pelanggan mana pun.
MD,
    ],

    [
        'slug' => 'jenis-menu', 'judul' => 'Jenis Menu', 'kelompok' => 'Penjualan', 'urutan' => 70,
        'letak' => 'Penjualan → Jenis Menu',
        'ringkasan' => 'Daftar kelompok menu (mis. Dimsum, Gyoza) untuk Produk Jual & Bumbu Pusat. Dipakai Laporan Laba Rugi "Per Jenis Menu".',
        'izin' => ['jenis-olahan.view'],
        'route_names' => ['master.jenis-olahan.*'],
        'terkait' => ['produk-jual', 'master-bumbu-pusat'],
        'kata_kunci' => 'jenis menu jenis olahan kelompok menu dimsum gyoza laba rugi per jenis',
        'konten' => <<<'MD'
## Fungsi
Nama-nama kelompok menu. Dipilih di form **Produk Jual** (kolom Jenis Menu) dan **Master Bumbu Pusat**, lalu dipakai **Laporan Laba Rugi → Per Jenis Menu** untuk melihat untung per kelompok.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Master Jenis Menu**; tambah/ubah perlu **Kelola Master Jenis Menu**.
- Supaya laporan terisi, setiap Produk Jual perlu dipilih Jenis Menu-nya (Produk Jual → Edit → Jenis Menu).

## Akibat ke data lain
- **Tidak** mengubah stok, kas, harga, atau tampilan POS.
- Order baru menyimpan nama Jenis Menu produk saat dijual. Order lama memakai Jenis Menu produknya yang sekarang. Produk tanpa Jenis Menu masuk "-".

## Menu yang mirip & bedanya
- **Kategori produk** (dipilih di form Produk Jual) — inilah yang tampil sebagai chip filter di POS dan dipakai Laporan Kategori. Jenis Menu bukan kategori produk.
MD,
    ],

    [
        'slug' => 'master-bumbu-pusat', 'judul' => 'Master Bumbu Pusat', 'kelompok' => 'Penjualan', 'urutan' => 80,
        'letak' => 'Penjualan → Master Bumbu Pusat',
        'ringkasan' => 'Resep campuran (bumbu/adonan) yang bisa ditautkan ke banyak produk jual sekaligus. Ubah sekali, semua produk ikut.',
        'izin' => ['master.resep_bumbu.view'],
        'route_names' => ['master.resep-bumbu.*'],
        'terkait' => ['produk-jual', 'bahan-baku', 'jenis-menu', 'cara-membuat-resep'],
        'kata_kunci' => 'bumbu pusat resep bumbu resep standar adonan campuran saus isian komposisi bersama import bumbu',
        'konten' => <<<'MD'
## Fungsi
Membuat satu resep campuran (mis. adonan isian, saus) yang dipakai banyak produk. Di form Produk Jual, tombol **"Import dari Bumbu Pusat"** menautkan bumbu ini ke komposisi produk — **ditautkan, bukan disalin**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat/Tambah/Edit/Hapus Master Bumbu Pusat**.
- Bumbu Pusat **milik cabang** (ikut cabang di pojok kanan atas). Isi bumbu hanya boleh bahan **milik cabang yang sama**.
- Supaya bisa dipakai, bumbu harus diimport ke komposisi Produk Jual (izin Edit Produk Jual).

## Apa yang terjadi
| Aksi | Akibat |
|---|---|
| Tambah / ubah takaran bahan | **Semua produk yang menautkan bumbu ini langsung ikut** — HPP di POS dan bahan yang dipotong saat jual memakai takaran baru mulai transaksi berikutnya |
| Bumbu dinonaktifkan | Tidak bisa diimport ke produk baru, tapi produk yang sudah menautkan **tetap jalan** |
| Produk dijual di POS | Bahan di dalam bumbu ikut dipotong dari stok cabang (takaran bumbu × jumlah di komposisi produk) |

Harga yang tampil = harga FIFO bahan (batch tertua yang masih ada); hanya perkiraan, HPP sebenarnya dihitung saat penjualan.

## Wajib
- Isi bumbu hanya **bahan langsung** — bumbu tidak bisa berisi bumbu lain.

## Jangan
- Jangan mengubah takaran bumbu untuk "mencoba-coba" di jam jualan — semua produk yang menautkannya langsung berubah.

## Menu yang mirip & bedanya
- **Komposisi di Produk Jual** — resep khusus 1 produk. Bumbu Pusat = resep bersama banyak produk.
- **Resep Produksi** (menu Produksi) — untuk membuat bahan setengah jadi di gudang menjadi stok, bukan dipotong saat jual.
MD,
    ],

    [
        'slug' => 'program-loyalty', 'judul' => 'Program Loyalty', 'kelompok' => 'Penjualan', 'urutan' => 90,
        'letak' => 'Penjualan → Program Loyalty',
        'ringkasan' => 'Program hadiah untuk pelanggan tetap: otomatis dari total belanja/jumlah transaksi, atau klaim manual yang disetujui.',
        'izin' => ['loyalty.view'],
        'route_names' => ['loyalty-program.*', 'loyalty-klaim.*'],
        'terkait' => ['pelanggan', 'pos'],
        'kata_kunci' => 'loyalty hadiah reward poin member voucher klaim pelanggan setia promo',
        'konten' => <<<'MD'
## Fungsi
Memberi hadiah ke pelanggan yang belanja terus. Ada 2 jenis program:
- **Auto-Track** — progres dihitung otomatis dari order pelanggan: total belanja (Rp), jumlah transaksi, atau kg (cara lama usaha giling — tidak untuk produk jual).
- **Event-based (klaim manual)** — pelanggan mengajukan klaim + bukti, lalu disetujui.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Program Loyalty**; buat/ubah program perlu **Kelola Program Loyalty**.
- Klaim punya centangan sendiri: **Lihat / Buat / Approve / Reject Klaim Loyalty**, **Tandai Voucher Klaim Diberikan**.
- Progres hanya bertambah kalau di POS **pelanggan dipilih** (bukan hanya ketik nama). Program "Penjualan" menghitung order penjualan; isi periode kalau program hanya berlaku di tanggal tertentu.
- Program berlaku untuk **semua cabang**.

## Alur
- Auto-Track: pelanggan belanja → progres naik → target tercapai → admin klik **Tandai Hadiah Diberikan**. Kalau "Bisa berulang" dicentang, pelanggan bisa dapat hadiah lagi di kelipatan target berikutnya.
- Klaim: Buat klaim → **Approve** atau **Reject** → **Tandai voucher diberikan**.

## Akibat ke data lain
- **Tidak** mengubah stok atau kas secara otomatis. Kalau hadiah berupa barang/uang, catat sendiri (Penyesuaian Stok / Kas & Transaksi).

## Jangan
- Jangan membuat data pelanggan dobel untuk orang yang sama — progresnya terpecah ke 2 data.
MD,
    ],

    // ===================== ANTRIAN PRODUKSI =====================

    [
        'slug' => 'cek-antrian', 'judul' => 'Cek Antrian', 'kelompok' => 'Antrian Produksi', 'urutan' => 10,
        'letak' => 'Antrian Produksi → Cek Antrian (juga tombol di POS)',
        'ringkasan' => 'Mencari order berdasarkan nomor antrian, melihat statusnya, mencetak ulang, dan menandai sudah diambil pelanggan.',
        'izin' => ['antrian.lihat'],
        'route_names' => ['antrian.cek', 'antrian.cek.*'],
        'terkait' => ['kelola-antrian', 'mode-produksi', 'display-tv'],
        'kata_kunci' => 'cek antrian nomor antrian pesanan diambil ambil pesanan status pesanan',
        'konten' => <<<'MD'
## Fungsi
Kasir mencari pesanan pelanggan dari nomor antrian, melihat sudah sampai mana, lalu menandai **Sudah Diambil** saat diserahkan.

## Supaya menu ini muncul & bisa dipakai
- Bagian sidebar **Antrian Produksi** hanya muncul kalau cabang aktif menyalakan **Sistem Antrian Produksi** (Cabang & Gudang → Edit) — atau saat "Semua Cabang".
- Centangan **Lihat Antrian Produksi**.
- Order dapat nomor antrian hanya kalau: antrian cabang menyala, tipe transaksi **Dine-in/Takeaway** (Frozen tidak), dan pilihan "Tampil di antrian" di POS tidak dimatikan.

## Status antrian
Menunggu → Dikerjakan → Selesai → Disimpan (di rak, opsional) → **Diambil**.

## Akibat ke data lain
Hanya status antrian. **Tidak** mengubah stok, kas, atau laporan penjualan.
MD,
    ],

    [
        'slug' => 'kelola-antrian', 'judul' => 'Kelola Antrian', 'kelompok' => 'Antrian Produksi', 'urutan' => 20,
        'letak' => 'Antrian Produksi → Kelola Antrian',
        'ringkasan' => 'Operator dapur mengatur antrian: mulai kerja, selesai, simpan di rak, diambil, ambil alih.',
        'izin' => ['antrian.kelola'],
        'route_names' => ['antrian.operator'],
        'terkait' => ['cek-antrian', 'mode-produksi', 'karyawan'],
        'kata_kunci' => 'kelola antrian operator dapur produksi pesanan mulai kerja selesai rak ambil alih',
        'konten' => <<<'MD'
## Fungsi
Layar operator untuk memproses pesanan sesuai urutan antrian.

## Supaya menu ini muncul & bisa dipakai
- Sistem Antrian Produksi menyala di cabang (Cabang & Gudang → Edit).
- Centangan **Kelola Antrian Produksi**.
- Karyawan yang mengerjakan harus terdaftar di menu **Karyawan** pada cabang ini (dipilih saat Mulai Kerja).

## Apa yang terjadi kalau tombol diklik
| Tombol | Syarat | Akibat |
|---|---|---|
| Mulai Kerja | Status Menunggu; pilih karyawan | Status → Dikerjakan |
| Selesai | Status Dikerjakan | Status → Selesai |
| Simpan Rak | Status Selesai | Status → Disimpan, lokasi rak tercatat |
| Diambil | Status Selesai atau Disimpan | Status → Diambil |
| Ambil Alih | Status Dikerjakan | Pekerjaan pindah ke karyawan lain (tercatat) |

**Tidak** mengubah stok, kas, atau laporan penjualan — stok sudah dipotong saat order dibayar di POS.

## Menu yang mirip & bedanya
- **Mode Produksi** — tombol yang sama untuk tablet bersama di dapur.
- **Produksi** (Stok & Gudang) — membuat bahan setengah jadi di gudang; bukan antrian pesanan pelanggan.
MD,
    ],

    [
        'slug' => 'mode-produksi', 'judul' => 'Mode Produksi', 'kelompok' => 'Antrian Produksi', 'urutan' => 30,
        'letak' => 'Antrian Produksi → Mode Produksi',
        'ringkasan' => 'Tampilan layar penuh Kelola Antrian untuk tablet bersama di dapur.',
        'izin' => ['antrian.kelola'],
        'route_names' => ['antrian.produksi', 'antrian.produksi.*'],
        'terkait' => ['kelola-antrian', 'display-tv'],
        'kata_kunci' => 'mode produksi tablet dapur layar dapur',
        'konten' => <<<'MD'
## Fungsi
Sama seperti **Kelola Antrian**, tapi dirancang untuk 1 tablet yang dipakai bergantian di dapur: setiap aksi memilih nama karyawan, jadi tidak perlu login per karyawan.

## Supaya menu ini muncul & bisa dipakai
- Sistem Antrian Produksi menyala di cabang; centangan **Kelola Antrian Produksi**.
- Nama karyawan diambil dari menu **Karyawan** (karyawan aktif cabang ini).

## Akibat ke data lain
Sama dengan Kelola Antrian — hanya status antrian.
MD,
    ],

    [
        'slug' => 'display-tv', 'judul' => 'Display TV Antrian', 'kelompok' => 'Antrian Produksi', 'urutan' => 40,
        'letak' => 'Antrian Produksi → Display TV',
        'ringkasan' => 'Layar TV untuk pelanggan: nomor antrian yang sedang dikerjakan dan siap diambil, plus teks berjalan.',
        'izin' => ['antrian.display'],
        'route_names' => ['antrian.display'],
        'terkait' => ['cek-antrian', 'cabang'],
        'kata_kunci' => 'display tv layar antrian televisi running text teks berjalan',
        'konten' => <<<'MD'
## Fungsi
Membuka halaman untuk ditampilkan di TV toko. Nomor antrian berubah otomatis.

## Supaya menu ini muncul & bisa dipakai
- Sistem Antrian Produksi menyala; centangan **Display TV Antrian**.
- Harus memilih **cabang tertentu** di pojok kanan atas (saat "Semua Cabang" menu ini tidak tampil).
- **Teks berjalan** diatur di Cabang & Gudang → Edit → "Tampilan Display TV Antrian" (nyalakan + isi teks).
- Logo & nama yang tampil diambil dari Pengaturan Umum.

## Akibat ke data lain
Hanya menampilkan. Halaman TV bisa dibuka tanpa login (supaya TV tidak perlu akun).
MD,
    ],
];
