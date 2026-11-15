<?php

/**
 * 4.50 Kamus Menu — kelompok Pengaturan, Bantuan, Keamanan.
 * Fakta diambil dari kode. Kalau perilaku menu berubah, ubah juga teks di sini.
 */
return [

    // ===================== PENGATURAN =====================

    [
        'slug' => 'cabang', 'judul' => 'Cabang & Gudang', 'kelompok' => 'Pengaturan', 'urutan' => 10,
        'letak' => 'Pengaturan → Cabang & Gudang',
        'ringkasan' => 'Daftar lokasi usaha (cabang & Gudang Pusat) beserta semua pengaturan per lokasi: POS, struk, antrian, label, absensi, Kirim ke Cabang.',
        'izin' => ['cabang.view'],
        'route_names' => ['cabang.index', 'cabang.create', 'cabang.edit', 'cabang.show', 'cabang.toggle-aktif', 'cabang.assign-user'],
        'terkait' => ['pos', 'kiriman-cabang', 'scan-absensi', 'display-tv', 'kas-transaksi', 'manajemen-user', 'cara-produk-di-banyak-cabang'],
        'kata_kunci' => 'cabang gudang outlet lokasi tambah cabang pengaturan cabang config pos struk antrian label gps radius absen jam masuk kirim ke cabang wajib bukti service charge take away fee',
        'konten' => <<<'MD'
## Fungsi
Mendaftarkan lokasi usaha. Banyak fitur di menu lain **hanya muncul kalau diaktifkan di sini**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus Cabang**.
- Tipe **Gudang Pusat** dibutuhkan untuk: Produksi, Kirim ke Cabang, tujuan Setoran Kasir, Dashboard Gudang.

## Pengaturan di Edit Cabang & menu yang terpengaruh
| Pengaturan | Mengubah menu |
|---|---|
| Alamat GPS (latitude/longitude) + **Radius absen** | Scan Absensi menolak absen di luar radius (kosong = tidak dicek) |
| **Jam masuk** | Batas terlambat untuk karyawan tanpa shift |
| Sistem Antrian Produksi | Bagian sidebar Antrian Produksi + nomor antrian di POS |
| Running text | Teks berjalan di Display TV |
| Struk: sembunyi harga per item, footer struk | Struk POS |
| **Label Produk**: aktif, kategori, ukuran, geser posisi | Tombol cetak label di POS & Detail Order |
| **Config POS**: Dine-in/Takeaway/Frozen, pilihan tipe & tipe bawaan, nomor meja, take away fee, service charge | Tampilan & total POS |
| Wajib bukti Transfer / QRIS | Unggah bukti wajib saat bayar |
| **Aktifkan Kirim ke Cabang** (hanya Gudang Pusat, bawaan mati) | Kotak Jenis Pembeli di POS gudang |
| Kepala cabang, user ditugaskan | Siapa yang bisa memilih cabang ini di pojok kanan atas |

Saat **Tambah Cabang**, pilihan "Salin Harga & Pengaturan Barang dari" menyalin semua bahan, produk, komposisi, Bumbu Pusat, dan Qty Minimum dari cabang lain (stok tidak).

## Wajib setelah membuat cabang baru
1. Buat 3 kas (Default untuk Tunai, Transfer, QRIS) di Kas & Transaksi → Kelola Kas.
2. Isi barang (salin dari cabang lain atau buat baru) + stok awal.
3. Tugaskan user ke cabang (Manajemen User / Edit Cabang).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Nonaktifkan | Cabang hilang dari pilihan, data tetap |
| **Hapus** | ⚠️ Cabang **beserta order, PO, stok, permintaan/transfer stok, absensi, dan gaji**-nya disembunyikan (Data Terhapus) — hilang dari semua laporan |

## Jangan
- **Jangan hapus cabang yang sudah pernah beroperasi** — pakai **Nonaktifkan**.
MD,
    ],

    [
        'slug' => 'manajemen-user', 'judul' => 'Manajemen User', 'kelompok' => 'Pengaturan', 'urutan' => 20,
        'letak' => 'Pengaturan → Manajemen User',
        'ringkasan' => 'Akun login: email, password, role (hak akses), cabang yang boleh dibuka, aktif/nonaktif.',
        'izin' => ['user.view'],
        'route_names' => ['user.*'],
        'terkait' => ['role-hak-akses', 'karyawan', 'cabang'],
        'kata_kunci' => 'user akun login password reset password role cabang user nonaktif tambah user kasir baru',
        'konten' => <<<'MD'
## Fungsi
Membuat dan mengelola akun yang bisa login.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat / Tambah / Edit / Hapus User** (reset password & aktif/nonaktif ikut centangan Edit).

## Aturan penting
- Role **Owner** hanya bisa diberikan, diubah, dinonaktifkan, atau dihapus **oleh Owner**.
- Role yang tidak boleh melihat semua cabang hanya mengelola user yang berbagi cabang dengannya, dan hanya bisa menugaskan ke cabangnya sendiri.
- Tidak bisa menghapus/menonaktifkan akun sendiri, atau menghapus satu-satunya Owner.
- **Cabang default** = cabang yang terpilih otomatis saat login.

## Apa yang terjadi
| Tombol | Akibat |
|---|---|
| Ganti role | Menu & tombol yang terlihat langsung berubah sesuai centangan role baru |
| Nonaktifkan | Tidak bisa login, data tetap |
| Reset password | Password baru minimal 8 karakter |

## Menu yang mirip & bedanya
- **Karyawan** — data kepegawaian; bisa membuat akun login juga (dengan izin Tambah User).
- **Role & Hak Akses** — mengatur centangan untuk tiap role.
MD,
    ],

    [
        'slug' => 'role-hak-akses', 'judul' => 'Role & Hak Akses', 'kelompok' => 'Pengaturan', 'urutan' => 30,
        'letak' => 'Pengaturan → Role & Hak Akses',
        'ringkasan' => 'Mengatur centangan izin untuk setiap role, dan membuat role sendiri (mis. "Kru").',
        'izin' => ['@owner'],
        'route_names' => ['role.*'],
        'terkait' => ['manajemen-user'],
        'kata_kunci' => 'role hak akses izin permission centang menu tidak muncul akses ditolak 403 role baru',
        'konten' => <<<'MD'
## Fungsi
Menentukan menu & tombol apa saja yang bisa dipakai setiap role.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner** (Owner selalu bisa semua menu).

## Cara kerjanya
- Setiap menu punya centangan sendiri; tombol penting (hapus, batalkan, approve) juga punya centangan sendiri. **Centangan selalu berlaku** — kalau tidak dicentang, menu tidak tampil dan alamatnya ditolak.
- Centangan berlabel "(tidak dipakai — centangan tidak berpengaruh)" boleh diabaikan.
- **Role buatan** bisa ditambah/diubah/dihapus (hapus ditolak kalau masih dipakai user). Role buatan bisa diberi akses semua cabang atau tidak.
- Yang **tidak bisa** diberikan ke role lain: Role & Hak Akses dan Pengaturan Umum (khusus Owner).

## Tips kalau ada yang bilang "menu tidak muncul"
1. Cek centangan menu itu di role user tersebut.
2. Cek **Kamus Menu** menu itu → bagian "Supaya menu ini muncul" (sering ada saklar di menu lain, mis. Antrian/Label/Kirim ke Cabang di Edit Cabang).
3. Cek cabang di pojok kanan atas.
MD,
    ],

    [
        'slug' => 'pengaturan-umum', 'judul' => 'Pengaturan Umum', 'kelompok' => 'Pengaturan', 'urutan' => 40,
        'letak' => 'Pengaturan → Pengaturan Umum',
        'ringkasan' => 'Identitas usaha (nama, logo, tagline, telepon), warna aplikasi, dan tanggal mulai periode Tutup Buku.',
        'izin' => ['@owner'],
        'route_names' => ['pengaturan.umum', 'pengaturan.umum.*'],
        'terkait' => ['closing', 'display-tv'],
        'kata_kunci' => 'pengaturan umum logo nama usaha tagline warna aplikasi tema periode tutup buku tanggal mulai periode',
        'konten' => <<<'MD'
## Fungsi
Pengaturan yang berlaku untuk **seluruh usaha**.

## Supaya menu ini muncul & bisa dipakai
- **Khusus Owner**.

## Isian & akibatnya
| Isian | Tampil di |
|---|---|
| Nama usaha, nama singkat, tagline, telepon | Sidebar, login, struk, PDF laporan, slip gaji, TV Antrian, aplikasi kasir |
| Logo | Sidebar, login, struk, PDF, ikon aplikasi (PWA) |
| **Warna aplikasi** (3 warna / tema siap pakai) | Sidebar, tombol utama, latar, grafik, PDF & Excel. Warna latar harus terang |
| **Tanggal mulai periode** (1–28) | Periode Tutup Buku Bulanan (Closing) |

## Jangan
- Jangan mengubah tanggal mulai periode setelah ada Tutup Buku — periode berikutnya jadi tidak sejajar dengan yang sudah dikunci.
MD,
    ],

    [
        'slug' => 'pengaturan-penggajian', 'judul' => 'Pengaturan Penggajian', 'kelompok' => 'Pengaturan', 'urutan' => 50,
        'letak' => 'Pengaturan → Pengaturan Penggajian',
        'ringkasan' => 'Tarif dasar perhitungan slip gaji: tarif lembur, potongan alpa per hari, hari kerja per minggu.',
        'izin' => ['pengaturan_penggajian'],
        'route_names' => ['pengaturan.penggajian', 'pengaturan.penggajian.*'],
        'terkait' => ['penggajian', 'hari-libur'],
        'kata_kunci' => 'pengaturan penggajian tarif lembur potongan alpa hari kerja per minggu potongan telat',
        'konten' => <<<'MD'
## Fungsi
Angka dasar yang dipakai **Generate Gaji**.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Pengaturan Tarif Penggajian**.

## Isian & akibatnya
| Isian | Akibat di slip gaji |
|---|---|
| Tarif lembur per jam | Uang lembur = jam lembur × tarif (0 = gaji pokok ÷ 173 × 1,5) |
| Potongan alpa per hari | Dikalikan jumlah Alpha |
| Hari kerja per minggu (5/6) | 5 = Sabtu ikut libur saat menghitung hari kerja |
| Potongan telat per menit | ⚠️ **Belum dipakai** dalam perhitungan saat ini |

Perubahan berlaku untuk slip yang di-Generate **setelahnya**.
MD,
    ],

    [
        'slug' => 'tooltip-helper', 'judul' => 'Tooltip Helper', 'kelompok' => 'Pengaturan', 'urutan' => 60,
        'letak' => 'Pengaturan → Tooltip Helper',
        'ringkasan' => 'Mengubah teks bantuan kecil (ikon ?) yang muncul di samping isian form.',
        'izin' => ['panduan.kelola'],
        'route_names' => ['admin.tooltips.*'],
        'terkait' => ['panduan-helper'],
        'kata_kunci' => 'tooltip bantuan ikon tanda tanya teks bantuan',
        'konten' => <<<'MD'
## Fungsi
Mengedit isi tooltip (ikon ? di form).

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Panduan & Tooltip**.

## Perlu diketahui
- Pembaruan aplikasi bisa menulis ulang sebagian tooltip bawaan. Catat perubahan penting Anda.
MD,
    ],

    [
        'slug' => 'panduan-helper', 'judul' => 'Panduan Helper', 'kelompok' => 'Pengaturan', 'urutan' => 70,
        'letak' => 'Pengaturan → Panduan Helper',
        'ringkasan' => 'Mengubah isi panduan (tombol "Cara Pakai") setiap menu.',
        'izin' => ['panduan.kelola'],
        'route_names' => ['admin.panduan.*'],
        'terkait' => ['panduan', 'tooltip-helper'],
        'kata_kunci' => 'panduan helper edit panduan cara pakai',
        'konten' => <<<'MD'
## Fungsi
Menambah/mengedit/menonaktifkan panduan langkah-langkah.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Kelola Panduan & Tooltip**.

## Perlu diketahui
- Pembaruan aplikasi bisa menambah bagian baru atau menulis ulang panduan tertentu. Kamus Menu **tidak** bisa diedit di sini — isinya ikut pembaruan aplikasi.
MD,
    ],

    // ===================== BANTUAN =====================

    [
        'slug' => 'panduan', 'judul' => 'Panduan', 'kelompok' => 'Bantuan', 'urutan' => 10,
        'letak' => 'Bantuan → Panduan (juga tombol "Cara Pakai" di setiap halaman)',
        'ringkasan' => 'Langkah-langkah cara memakai setiap menu.',
        'izin' => [],
        'route_names' => ['panduan.*'],
        'terkait' => ['kamus-menu', 'panduan-helper'],
        'kata_kunci' => 'panduan cara pakai langkah tutorial bantuan',
        'konten' => <<<'MD'
## Fungsi
Panduan langkah demi langkah ("klik ini, isi itu").

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user yang login.

## Menu yang mirip & bedanya
- **Kamus Menu** — fungsi, syarat muncul, alur, dan **akibat** setiap tombol ke stok/kas/laporan, plus perbandingan beberapa cara. Panduan = cara klik.
MD,
    ],

    [
        'slug' => 'kamus-menu', 'judul' => 'Kamus Menu', 'kelompok' => 'Bantuan', 'urutan' => 20,
        'letak' => 'Bantuan → Kamus Menu (juga tombol "Kamus" di atas setiap halaman)',
        'ringkasan' => 'Penjelasan setiap menu: fungsi, syarat supaya muncul, alur, akibat tiap tombol, wajib, jangan, dan menu yang mirip.',
        'izin' => [],
        'route_names' => ['kamus-menu.*'],
        'terkait' => ['panduan', 'role-hak-akses'],
        'kata_kunci' => 'kamus menu penjelasan menu fungsi menu cari menu bantuan',
        'konten' => <<<'MD'
## Fungsi
Tempat mencari penjelasan menu dengan kata sehari-hari, mis. "pindah barang", "batal order", "untung".

## Supaya menu ini muncul & bisa dipakai
- Bisa dibuka semua user; yang tampil hanya penjelasan menu yang **boleh Anda buka**.

## Cara memakai
- Ketik pertanyaan di kotak cari → klik hasil.
- Bagian **Cara Melakukan** membandingkan beberapa cara untuk tujuan yang sama.
- Tombol **Kamus** di bagian atas halaman langsung membuka penjelasan halaman yang sedang dibuka.
MD,
    ],

    // ===================== KEAMANAN =====================

    [
        'slug' => 'audit-log', 'judul' => 'Audit Log', 'kelompok' => 'Keamanan', 'urutan' => 10,
        'letak' => 'Keamanan → Audit Log',
        'ringkasan' => 'Catatan siapa melakukan apa dan kapan (membuat, mengubah, menghapus data).',
        'izin' => ['lihat_audit_log'],
        'route_names' => ['audit-log.*'],
        'terkait' => ['data-terhapus'],
        'kata_kunci' => 'audit log riwayat aktivitas siapa yang mengubah jejak perubahan',
        'konten' => <<<'MD'
## Fungsi
Melacak perubahan data: siapa, kapan, data lama → data baru.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Audit Log**.

## Hasil
Hanya menampilkan data.
MD,
    ],

    [
        'slug' => 'data-terhapus', 'judul' => 'Data Terhapus', 'kelompok' => 'Keamanan', 'urutan' => 20,
        'letak' => 'Keamanan → Data Terhapus',
        'ringkasan' => 'Tempat data yang dihapus (order, barang, kas, karyawan, cabang, dll). Bisa dipulihkan atau dihapus permanen.',
        'izin' => ['lihat_data_terhapus'],
        'route_names' => ['trash.*'],
        'terkait' => ['audit-log', 'riwayat-order', 'bahan-baku', 'backup'],
        'kata_kunci' => 'data terhapus tempat sampah restore pulihkan hapus permanen trash',
        'konten' => <<<'MD'
## Fungsi
Hampir semua tombol Hapus di aplikasi hanya **menyembunyikan** data. Di sini data itu bisa dilihat dan dipulihkan.

## Supaya menu ini muncul & bisa dipakai
- Melihat: **Lihat Data Terhapus**. Pulihkan: centangan restore. Hapus permanen: **Hapus Permanen Data**.
- Pilih jenis data di tab (Orders, Items, Kas, Karyawan, …).

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| **Pulihkan** | Data kembali beserta data yang dulu ikut terhapus bersamanya (mis. pulihkan barang → baris stok/PO lamanya ikut kembali). Order yang dipulihkan kembali masuk laporan apa adanya |
| **Hapus Permanen** | Hilang selamanya, tidak bisa dibatalkan |

## Jangan
- Jangan memulihkan barang dobel yang sudah punya pengganti — stoknya bisa jadi dobel.
- Order yang dulu dihapus dalam keadaan Selesai (cara lama): pulihkan lalu **Batalkan** supaya stok & kas ikut kembali.
MD,
    ],

    [
        'slug' => 'backup', 'judul' => 'Backup Database', 'kelompok' => 'Keamanan', 'urutan' => 30,
        'letak' => 'Keamanan → Backup Database',
        'ringkasan' => 'Membuat, mengunduh, dan menghapus file cadangan database.',
        'izin' => ['lihat_backup'],
        'route_names' => ['backup.*'],
        'terkait' => ['data-terhapus'],
        'kata_kunci' => 'backup cadangan database unduh backup restore',
        'konten' => <<<'MD'
## Fungsi
Menyimpan salinan seluruh database.

## Supaya menu ini muncul & bisa dipakai
- Centangan **Lihat Backup**, **Buat Backup**, **Download Backup**, **Hapus File Backup**.
- Backup otomatis harian hanya berjalan kalau server menjalankan jadwal otomatis (cron).

## Wajib
- **Unduh** backup secara rutin dan simpan di luar server, terutama sebelum pembaruan aplikasi.

## Perlu diketahui
- Mengembalikan (restore) backup tidak bisa dari menu ini — dilakukan lewat hosting (phpMyAdmin).
MD,
    ],
];
