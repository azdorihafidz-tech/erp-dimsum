# Rancangan Paket Modul, Masa Langganan & Akun Tim IT — ERP D'mentai

Status: **DISETUJUI Owner** (2026-11-17) — dikerjakan bertahap.

**Keputusan akhir Owner:** Q1 pembagian modul pas · Q2 paket hanya tombol cepat, modul tetap bebas diaktifkan di luar paket (tampil "Kustom") · Q3 baca-saja · Q4 tenggang 7 hari, peringatan H-14 · Q5 segel rusak = **mode lunak**: aplikasi tetap jalan memakai isi yang ada, hanya Teknisi yang melihat pita merah + notifikasi (database & cPanel semua pelanggan dipegang tim IT) · **Verifikasi dua langkah DITUNDA** — gantinya: kata sandi Teknisi minimal 12 karakter & berbeda per instalasi, batas salah login tetap aktif, semua login & tindakan Teknisi tercatat di Aktivitas Tim IT.
Keputusan yang sudah ada: modul dipilih **per modul** (+ paket siap pakai), modul yang dimatikan **disembunyikan, datanya aman**, **satu akun Teknisi per orang IT**, "masuk sebagai user" **ditunda**, **masa aktif langganan dibuat sekarang**. Kamus Menu & Panduan wajib.
Deploy digabung dengan 4.50–4.52 (skrip deploy akan diperbarui).

---

## 1. Kondisi sekarang (hasil audit)

| Bagian | Kondisi | Akibat |
|---|---|---|
| Owner | `Gate::before` → Owner selalu boleh semua; `User::hasPermission()` juga. Dicek khusus di **26 tempat / 14 file** (buka kunci Tutup Buku, kelola akun Owner, edit order, Neraca, menu Role & Hak Akses, Pengaturan Umum, dll). | Owner tetap penuh — **tidak** diubah jadi role centangan. |
| Izin | 244 izin dalam 20 kelompok (penjualan, stok, pembelian, keuangan, laporan, hr, aset, loyalty, …). Sidebar & tombol memakai `@can` / `hasPermission`. | Kalau izin "dimatikan" di satu titik pusat, menu, tombol, Kamus otomatis ikut hilang. |
| Role | `users.role` teks (role sistem + role buatan, 4.38). Middleware `role:owner` hanya untuk Role & Hak Akses dan Pengaturan Umum. | Role baru `teknisi` bisa ditambah tanpa migration kolom. |
| Login | Laravel Breeze biasa (email + kata sandi), tanpa verifikasi dua langkah. | Verifikasi dua langkah untuk Teknisi dibuat sendiri (tanpa paket tambahan). |
| Pekerjaan otomatis | Transaksi Berulang & pengingat opname dipicu saat aplikasi dibuka. | Harus ikut berhenti kalau modulnya mati / langganan habis. |
| Instalasi | Tiap pelanggan = instalasi & database sendiri, server dipegang tim IT. | Paket & masa aktif disimpan **per instalasi**. |

---

## 2. Tiga lapis

| Lapis | Diatur oleh | Isi |
|---|---|---|
| **Paket** (modul + masa aktif) | **Hanya Teknisi** | Modul mana yang aktif, sampai tanggal berapa langganan berlaku |
| **Owner** | Pelanggan | Penuh, **di dalam paketnya** — seperti sekarang |
| **Role lain** | Owner | Centangan Role & Hak Akses — seperti sekarang (izin modul yang mati tidak tampil) |

---

## 3. Daftar modul (usulan)

**Selalu aktif (inti, tidak bisa dimatikan):** Dashboard, Cabang, User, Role & Hak Akses, Pengaturan Umum, **Kas & Transaksi + Kelola Kas** (dibutuhkan POS untuk mencatat uang), Kamus Menu, Panduan, Keamanan (backup, data terhapus, log), Notifikasi, Profil.

| Modul | Menu & izin yang ikut | Butuh modul |
|---|---|---|
| **Penjualan & POS** | POS, Riwayat Order, Pelanggan, Jenis Menu, Label Produk, Laporan Transaksi/Produk/Kategori kasir, Laporan Penjualan, Setoran Kasir, buka laci | — |
| **Antrian Produksi** | Cek/Kelola Antrian, Mode Produksi, Display TV | Penjualan |
| **Program Loyalty** | Program Loyalty, klaim hadiah | Penjualan |
| **Stok & Gudang** | Dashboard Stok, Stok Barang, Penyesuaian, Permintaan & Transfer Stok, Bahan Baku, Produk Jual, Master Bumbu Pusat, Semua Barang, Pemakaian Perlengkapan, Laporan Stok / Perlengkapan / Konsumsi Bahan | — |
| **Produksi** | Produksi & Resep Produksi | Stok |
| **Kirim ke Cabang & Retur** | Kiriman Cabang, Retur Cabang, mode Kirim ke Cabang di POS gudang | Stok, Penjualan |
| **Stok Opname** | Stok Opname, Stok Rusak, Laporan Kerugian Stok, Ganti Rugi Stok | Stok (ganti rugi potong gaji butuh Penggajian) |
| **Pembelian** | Purchase Order, Supplier, Dashboard PO | Stok |
| **Keuangan Lanjutan** | Transfer Antar Kas, Transfer Dana, Transaksi Berulang, Kategori Transaksi, Closing / Tutup Buku, Analisis BEP, Laporan Keuangan / Saldo Kas / Transfer Dana / Per Kategori / Audit Bukti / Setoran Harian | — |
| **Akuntansi** | COA, Neraca, Laba Rugi Formal, Buku Besar, Laporan Eksekutif | Keuangan Lanjutan |
| **Analisis Bisnis** | Laporan BEP & BEP Otomatis, Simulator BEP, Jam Ramai, Laba Rugi Produksi, Cabang vs Cabang, Ranking Kasir, Dashboard Semua Cabang analitik | Penjualan |
| **SDM & Absensi** | Karyawan, Absensi (scan, manual, wajah, device), Shift, Hari Libur, Cuti, Laporan Absensi | — |
| **Penggajian** | Penggajian, Pengaturan Penggajian, Laporan Penggajian | SDM |
| **Penilaian 360°** | Evaluasi, Laporan Evaluasi | SDM |
| **Aset** | Manajemen Aset, penyusutan, Laporan Aset | — |

Modul yang dibutuhkan ikut aktif otomatis (mis. menyalakan Produksi → Stok ikut nyala; mematikan Stok → Produksi/Opname/Pembelian/Kiriman ikut mati, dengan konfirmasi).

**Paket siap pakai** (tombol cepat; setelah dipilih tetap bisa diubah per modul):

| Paket | Modul |
|---|---|
| **Dasar** | Penjualan & POS, Stok & Gudang |
| **Menengah** | Dasar + Pembelian, Antrian, Loyalty, Keuangan Lanjutan, SDM & Absensi, Penggajian, Stok Opname |
| **Lengkap** | Semua modul |

**Setelah deploy, semua instalasi yang sudah ada = Lengkap, tanpa batas tanggal** → tidak ada yang berubah sampai Teknisi mengaturnya.

---

## 4. Cara modul dimatikan (aman, satu titik)

- **Satu peta** `App\Support\Paket`: izin → modul, nama route → modul. Izin/route yang tidak terdaftar = inti (selalu aktif), jadi lupa memetakan tidak pernah membuat fitur hilang.
- **`Gate::before` & `User::hasPermission()`**: izin milik modul yang mati selalu **ditolak — termasuk untuk Owner**. Akibatnya otomatis: menu & bagian sidebar hilang, tombol hilang, entri Kamus hilang.
- **Middleware `paket`** di semua route web: alamat milik modul yang mati → halaman "Fitur ini tidak termasuk paket Anda" (bukan error).
- **Role & Hak Akses**: izin modul yang mati tidak ditampilkan (centangan lama tetap tersimpan → muncul lagi kalau modul dinyalakan).
- **Pekerjaan otomatis** (Transaksi Berulang, pengingat opname, penyusutan aset) dilewati kalau modulnya mati.
- **Data tidak pernah dihapus.** Menyalakan lagi = semua kembali seperti semula.

---

## 5. Masa aktif langganan

Diatur Teknisi: **Aktif sampai** (kosong = tanpa batas) dan **Masa tenggang** (bawaan 7 hari).

| Waktu | Yang terjadi |
|---|---|
| H-14 sampai tanggal berakhir | Pita kuning di atas halaman untuk Owner (& Teknisi): "Langganan berakhir dd/mm/yyyy, hubungi tim IT" |
| Lewat tanggal, dalam masa tenggang | Pita merah untuk **semua user**, aplikasi tetap berjalan normal |
| Masa tenggang habis | **Mode baca-saja**: semua user tetap bisa login, melihat data & laporan, mengunduh PDF/Excel. **Semua simpan/ubah/hapus ditolak** (termasuk transaksi POS & pekerjaan otomatis). Pesan jelas di setiap halaman |
| Teknisi | Tidak terkena mode baca-saja (supaya bisa memperbaiki/memperpanjang) |

Pengecualian di mode baca-saja: login, logout, ganti kata sandi sendiri.

---

## 6. Penyimpanan paket — tahan diubah

- Tabel baru `lisensi` (1 baris per instalasi): modul aktif, aktif sampai, masa tenggang, diubah oleh, kapan.
- Isinya **ditandatangani** dengan kunci aplikasi (`APP_KEY`, HMAC). Kalau ada yang mengubah tabel langsung di database, tanda tangan tidak cocok → aplikasi masuk **mode baca-saja** dengan pesan "Lisensi tidak valid, hubungi tim IT" sampai Teknisi menyimpan ulang.
- Setiap perubahan paket tercatat di log aktivitas (lama → baru).
- Catatan: `APP_KEY` tidak boleh diganti (sudah dipakai juga untuk sesi & data terenkripsi).

---

## 7. Akun Tim IT (Teknisi)

- Role sistem baru **`teknisi`** (label "Tim IT"): akses semua cabang, semua izin modul yang aktif, plus menu khusus **Paket & Langganan** dan **Tim IT**.
- **Tersembunyi dari pelanggan**: tidak muncul di Manajemen User, Role & Hak Akses, pilihan role, daftar penerima notifikasi pelanggan. Owner tidak bisa mengubah/menghapus/menonaktifkan akun Teknisi.
- **Per orang**: Teknisi menambah/menonaktifkan Teknisi lain di menu **Tim IT**.
- **Teknisi pertama** dibuat oleh skrip deploy dari baris `.env` (`TEKNISI_NAMA`, `TEKNISI_EMAIL`, `TEKNISI_PASSWORD`, pola go-live 4.28) — kata sandi tidak dicetak di layar. Baris `.env` dihapus setelah deploy.
- **Verifikasi dua langkah wajib** (kode 6 angka dari aplikasi Google Authenticator / Microsoft Authenticator): pertama kali login → pindai kode QR; setiap login berikutnya → isi kode. 8 **kode cadangan** sekali pakai kalau HP hilang. Teknisi lain bisa mengatur ulang verifikasi temannya.
- Semua yang setara Owner (26 tempat) memakai satu pengecekan baru **"akses penuh" = Owner atau Teknisi**, supaya Teknisi bisa membantu di semua menu.

### Aktivitas Tim IT (untuk kepercayaan pelanggan)
- Setiap login Teknisi dan setiap simpan/ubah/hapus yang dilakukan Teknisi dicatat (waktu, nama, menu, alamat IP).
- Menu **Keamanan → Aktivitas Tim IT** untuk **Owner** (dan Teknisi): daftar catatan itu. Owner tidak bisa menghapusnya.

---

## 8. Kamus Menu & Panduan

- Panduan baru: `paket-langganan` (untuk Teknisi), `tim-it` (Teknisi), `aktivitas-tim-it` (Owner), dan bagian di panduan `role-hak-akses` ("izin modul yang tidak termasuk paket tidak tampil").
- Kamus baru: Paket & Langganan, Tim IT, Aktivitas Tim IT, dan **Cara Melakukan: "Kenapa menu/fitur tidak ada?"** (penyebab: tidak termasuk paket → hubungi tim IT; belum dicentang di Role & Hak Akses; mode baca-saja).
- Izin semu baru `@teknisi` di Kamus (seperti `@owner`), supaya entri khusus Teknisi hanya terlihat oleh Teknisi.
- Entri Kamus yang menunya mati otomatis tidak tampil (ikut izin).

---

## 9. Tahapan

| Tahap | Isi |
|---|---|
| **A** | Role Teknisi + "akses penuh" di 26 tempat + disembunyikan dari pelanggan + menu Tim IT + verifikasi dua langkah + kode cadangan + Aktivitas Tim IT |
| **B** | Tabel `lisensi` bertanda tangan + peta modul + penolakan di Gate/hasPermission/middleware + Role & Hak Akses menyaring + pekerjaan otomatis menyaring + halaman **Paket & Langganan** (modul, paket siap pakai, ketergantungan) |
| **C** | Masa aktif: pita H-14, masa tenggang, mode baca-saja, lisensi tidak valid |
| **D** | Panduan, tooltip, Kamus, pembaruan skrip deploy (+ pembuatan Teknisi pertama), regresi penuh |

Setiap tahap: feature test (termasuk: Owner tidak melihat modul mati & ditolak lewat alamat langsung, data modul mati tetap ada, nyalakan lagi kembali normal, mode baca-saja menolak POST tapi laporan tetap bisa dibuka, Teknisi tidak terlihat & tidak bisa diubah Owner, verifikasi dua langkah, lisensi diubah langsung di DB → baca-saja), commit & push terpisah.

---

## 10. Risiko

| Risiko | Penanganan |
|---|---|
| Izin/route lupa dipetakan | Dianggap inti (selalu aktif) — tidak pernah menghilangkan fitur tanpa sengaja. Tes penjaga: setiap link sidebar milik modul tertentu ikut hilang saat modulnya mati |
| Mode baca-saja memblokir POS di hari jualan | Pita peringatan dari H-14 + masa tenggang 7 hari; Teknisi bisa memperpanjang kapan saja tanpa deploy |
| Teknisi kehilangan HP | Kode cadangan; Teknisi lain bisa reset; skrip darurat sekali pakai |
| Mengubah 26 pengecekan Owner | Satu fungsi baru, diuji per tempat; perilaku Owner tidak berubah |
| Tes lama | Instalasi tes = Lengkap tanpa batas, jadi tes lama tidak terpengaruh |

---

## 11. Pertanyaan yang perlu Bapak putuskan

| No | Pertanyaan | Saran |
|---|---|---|
| **Q1** | Pembagian modul di bagian 3 sudah pas? (mis. Kas & Transaksi dijadikan inti karena POS membutuhkannya) | Ya, seperti tabel |
| **Q2** | Isi paket Dasar / Menengah / Lengkap | Seperti tabel (bisa diubah per pelanggan) |
| **Q3** | Setelah masa tenggang habis: **baca-saja** (masih bisa lihat & unduh laporan) atau **terkunci total** (hanya halaman "langganan berakhir")? | Baca-saja — data pelanggan tetap bisa diakses, lebih aman secara hubungan bisnis |
| **Q4** | Lama masa tenggang & kapan pita peringatan mulai | 7 hari tenggang, peringatan mulai H-14 |
| **Q5** | Kalau lisensi terdeteksi diubah langsung di database | Mode baca-saja sampai Teknisi menyimpan ulang |
