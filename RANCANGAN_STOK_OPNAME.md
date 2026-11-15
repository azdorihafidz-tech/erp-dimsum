# Rancangan Stok Opname — ERP D'mentai

Status: **RANCANGAN — menunggu persetujuan Owner** (2026-11-15)
Acuan: fitur Stok Opname ERP BABA, disesuaikan dengan kondisi D'mentai. Semua angka data di bawah diambil dari salinan database lokal (`erp_dimsum`).

---

## 1. Kondisi yang ditemukan

| Bagian | Kondisi sekarang | Akibat untuk opname |
|---|---|---|
| **Lokasi** | 1 Gudang Pusat + 6 cabang (tabel `cabangs`, tipe `gudang_pusat` / `cabang`). Tidak ada lokasi terpisah untuk dapur, gudang beku, atau freezer. | Opname dilakukan per **lokasi = cabang/gudang**. |
| **Outlet mitra / franchise** | Tidak ada. Semua lokasi milik pusat. | Aturan "opname outlet mitra disetujui pemilik outlet" **tidak dibuat**. |
| **Data barang** | Barang terpisah per cabang (4.39, `items.cabang_id`). Barang yang punya stok hanya **Bahan Baku, Kemasan, Tambahan Gratis** (`Item::TIPE_DAPAT_DIBELI`). Produk Jual tidak punya stok; yang dipotong bahan resepnya (4.34). | Cakupan opname = barang milik lokasi itu dari 3 tipe tersebut. Data lama tanpa cabang ikut kalau punya stok di lokasi itu. |
| **Stok** | `stocks` = jumlah per barang per lokasi (3 desimal). `stock_batches` = batch FIFO (jumlah sisa + harga per batch + tanggal masuk). `stock_movements` = kartu stok (masuk/keluar/transfer/penyesuaian), sudah punya `referensi_type`/`referensi_id`. | Nilai kerugian = harga batch FIFO yang dipakai, sama seperti HPP POS. Kartu stok bisa menautkan ke nomor SO. |
| **Kedaluwarsa per batch** | **Tidak ada.** Batch tidak menyimpan tanggal kedaluwarsa (yang ada hanya tanggal expired frozen di struk). | Penyebab "Basi/Kedaluwarsa" dipilih manual oleh penghitung, tanpa tanggal kedaluwarsa dari sistem. |
| **Barcode** | **Tidak ada** kolom barcode di barang. | Lihat pertanyaan **P5**. |
| **Satuan** | Teks bebas. Data: Pcs 237, porsi 216, Kg 5, Bks 5, Pack/Ikat/M/bal/Toples/sterofoam 1–2. **6 barang** (4 pcs, 2 porsi) stoknya sudah pecahan (mis. 12,5 pcs) karena resep memakai setengah pcs. | Lihat pertanyaan **P6**. |
| **Penyesuaian Stok (lama)** | Menimpa jumlah stok. Alasan susut/rusak/hilang → beban "Beban Kerugian Stok" (6-1401). **Alasan "Salah Hitung" dan "Audit Berkala (Stock Opname)" yang menurunkan stok TIDAK dicatat di keuangan** → kerugiannya tidak terlihat. Kenaikan → "Koreksi Stok Masuk" (4-1199), kecuali salah hitung/audit. | Bagian ini diperbaiki (bagian 8). |
| **Stok opname lama** | Tidak ada, hanya pilihan alasan "Audit Berkala" di Penyesuaian Stok. | Fitur baru penuh. |
| **Produksi** (4.49) | Bahan dipotong sesuai rencana, hasil nyata dicatat. Kalau hasil lebih sedikit (waste), biaya bahan dibagi ke hasil yang jadi → **waste produksi sudah masuk modal per unit hasil**. Tidak ada pencatatan waste tersendiri. | **Tidak tumpang tindih:** waste produksi sudah tercatat di HPP. Opname hanya menghitung selisih stok sesudahnya. Panduan akan menegaskan: waste saat produksi dicatat di hasil nyata Produksi, bukan lewat opname. |
| **Retur Cabang → Gudang** (4.49) | Barang basi dari cabang kembali ke stok gudang; pembuangannya sekarang lewat Penyesuaian Stok alasan rusak. | Setelah fitur ini, gudang bisa membuangnya lewat opname / stok rusak. Retur Cabang tidak diubah. |
| **Retur pembelian ke pemasok** | Tidak ada fitur. | "Kembali ke pemasok" hanya ada sebagai tindak lanjut stok rusak (bagian 5). |
| **Keuangan** | **Bukan jurnal berpasangan.** Setiap kejadian = 1 baris `transaksi_keuangans` dengan kategori → kode akun COA. Kas hanya berubah kalau baris itu punya `kas_id`. Laba Rugi Formal menampilkan semua akun COA (termasuk yang 0). Neraca: persediaan = nilai batch FIFO, modal = modal owner + laba ditahan (dari Laba Rugi). | Kerugian dicatat sebagai baris **beban tanpa kas**. Batch berkurang dan laba berkurang sama besar, jadi **Neraca tetap seimbang tanpa jurnal tambahan**. |
| **Catatan penting akuntansi** | Laba Rugi Formal menjumlah nilai per akun **tanpa melihat masuk/keluar**. Kalau pemasukan dicatat di akun beban, bebannya justru bertambah. | Kelebihan stok & pemulihan kerugian wajib memakai **akun pendapatan sendiri**. Pembatalan kerugian memakai **baris minus** di akun beban yang sama (pola Retur Cabang 4.49). |
| **Tutup Buku** (4.37) | Transaksi keuangan di tanggal yang sudah Closing Harian/Bulanan ditolak. | Lihat pertanyaan **P12**. |
| **Hak akses** | Izin per menu (4.43), role sistem 7 + role buatan, cabang per user (`CabangAktif`), kepala cabang (`cabangs.kepala_cabang_id`). Izin baru di production hanya dicentang bawaan **saat pertama kali dibuat** (insertOrIgnore). | Pola yang sama. |
| **Notifikasi** | Disimpan di database (lonceng), lewat `NotificationService`. Angka merah di menu sidebar sudah ada polanya (Kiriman, Retur, Setoran). | Dipakai ulang. |
| **Log aktivitas** | spatie activitylog, sudah dipakai di Pengaturan Umum. | Dipakai ulang untuk perubahan Pengaturan Stok Opname. |
| **Karyawan & gaji** | Slip gaji sudah punya kasbon & potongan lain (diisi manual). `total_gaji` = gaji bersih. Sejak 4.51, tombol Bayar mengurangi kas & mencatat beban Gaji; generate ulang tidak menimpa slip Disetujui/Dibayar. | Tambah satu kolom potongan khusus ganti rugi stok. |
| **Laporan** | dompdf (PDF) + maatwebsite (Excel). Sudah ada layout PDF laporan bersama dan style Excel bersama. | Dipakai ulang. |
| **Panduan / Tooltip / Kamus** | Seeder panduan & tooltip, Kamus Menu (4.50), tombol Cara Pakai. | Wajib diisi untuk semua menu baru. |
| **Tugas terjadwal** | `routes/console.php` memakai `Schedule::command()`. Hosting shared tanpa terminal; **belum dipastikan cron hosting menjalankan scheduler**. Ada pola pemicu saat halaman dibuka (Transaksi Berulang di `AppServiceProvider`). | Pengingat dijalankan **saat ada user membuka aplikasi** (dicek sekali per jam, aman dobel) + `Schedule::call()` sebagai cadangan. Tidak memakai `proc_open`/`exec`. Script deploy akan mencetak `disable_functions` hosting. |
| **Tes** | DB tes `erp_dimsum_test`. Regresi dijalankan per folder (Tahap25, Tahap5, Tahap6, Tahap7). | Hasil awal di bagian 12. |

---

## 2. Prinsip uang (dipakai apa adanya dari acuan)

1. Barang rusak/hilang **tidak memotong kas**. Yang turun adalah nilai persediaan; kerugian dicatat sebagai **beban** sehingga muncul di Laba Rugi.
2. Nilai kerugian = **harga pokok FIFO** (batch tertua yang masih ada di lokasi itu), bukan harga jual.
3. Setiap perubahan stok dari opname **selalu tercatat di keuangan**, termasuk salah catat (Selisih Persediaan).
4. Kas hanya berubah kalau ada uang sungguhan: ganti rugi karyawan tunai, atau pemasok membayar ganti barang rusak.
5. Stok & keuangan **baru berubah saat opname disetujui**. Yang diterapkan adalah **selisih** (fisik − stok sistem saat barang itu dihitung), bukan menimpa angka stok. Jadi penjualan/pemakaian sesudah barang dihitung tidak terhapus, dan **lokasi tetap boleh berjualan selama opname**.

Contoh: jam 08.00 stok sistem kulit dimsum 100 pcs, fisik 95 (selisih −5). Jam 10.00 POS memakai 20 pcs (stok 80). Saat disetujui jam 15.00, stok menjadi 80 − 5 = **75**, bukan 95.

---

## 3. Alur opname

1. **Mulai** — pilih lokasi, cakupan (semua barang / per kategori / barang tertentu), dan penghitung (satu atau beberapa user di lokasi itu). Nomor `SO-YYYYMM-NNNN`. Satu barang hanya boleh ada di **satu opname berjalan per lokasi**; kalau bentrok, ditolak dan nama opname lainnya disebutkan.
2. **Hitung** — daftar barang + kolom jumlah fisik, **tersimpan otomatis** tiap diisi (tanpa tombol Simpan). Pencarian cepat kode/nama barang; "scan" = ketik kode lalu Enter → +1 (lihat **P5**). Satuan berat/volume (kg, gram, ons, liter, ml) boleh desimal, satuan lain bulat.
3. **Hitung buta** (bawaan nyala, bisa dimatikan di Pengaturan) — penghitung tidak melihat stok sistem. Stok sistem dicatat per barang **saat barang itu dihitung**.
4. **Periksa selisih** — hanya pemeriksa/penyetuju yang melihat. Barang selisih bisa ditandai **"Hitung ulang"** (muncul lagi di daftar penghitung).
5. **Rincikan penyebab** per barang selisih. Satu barang boleh dipecah (kurang 5 = 2 rusak + 2 hilang + 1 sampel); jumlah rincian **wajib sama dengan selisih**. **Foto wajib** untuk Rusak/Basi/Freezer mati, dan untuk Hilang kalau nilainya ≥ batas di Pengaturan (bawaan Rp 200.000 per barang; 0 = semua Hilang wajib foto).
6. **Ajukan** → **Setujui** atau **Tolak** (alasan wajib, kembali ke tahap hitung). Penghitung tidak boleh menyetujui opname yang ia hitung sendiri, kecuali Owner.
7. **Disetujui** — stok & keuangan disesuaikan dalam **satu transaksi database** (baris stok & batch dikunci), lalu opname dikunci. Kalau stok sekarang sudah tidak cukup untuk dikurangi (keburu terjual/terpakai), persetujuan ditolak dengan pesan berisi nama barang, stok sekarang, dan pengurangan yang diminta. Tidak ada yang berubah sebagian.
8. **Batal** — hanya sebelum disetujui. Alasan wajib. Tidak ada yang berubah di stok/keuangan.

Status: **Menghitung → Diajukan → Disetujui**, atau **Diajukan → Ditolak (kembali Menghitung)**, atau **Dibatalkan**.

---

## 4. Penyebab & pencatatan (versi D'mentai)

Kerugian tidak memotong kas. Nilai = harga pokok FIFO.

| Penyebab | Stok | Keuangan (tanpa kas) | Akun COA |
|---|---|---|---|
| **Rusak** (pecah kemasan, penyok, jatuh, tercemar) | keluar → **stok rusak** (ditahan) | Beban Kerugian Stok – Rusak | **6-1402** (baru) |
| **Basi / Kedaluwarsa** | keluar → **stok rusak** (ditahan) | Beban Kerugian Stok – Basi/Kedaluwarsa | **6-1403** (baru) |
| **Freezer / pendingin mati** (cold chain putus) | keluar → **stok rusak** (ditahan) | Beban Kerugian Stok – Rusak (penyebab tetap terpisah di Laporan Kerugian) | 6-1402 |
| **Hilang** | keluar | Beban Kerugian Stok – Hilang (bisa dibebankan ke karyawan, bagian 6) | **6-1404** (baru) |
| **Susut** (berat berkurang, thawing, menguap) | keluar | Beban Penyusutan Stok | **6-1405** (baru) |
| **Sampel / tester / promosi** | keluar | Beban Promosi & Sampel | **6-1501** (baru) |
| **Makan karyawan** (usulan, lihat **P1**) | keluar | Beban Konsumsi | 6-1303 (sudah ada) |
| **Salah catat** (stok sistem kelebihan) | keluar | Selisih Persediaan | **6-1406** (baru) |
| **Lebih** (fisik > sistem) | masuk, **batch baru** dengan harga pokok rata-rata batch yang ada (kalau tidak ada: harga standar barang) | Selisih Lebih Persediaan | **7-1103** (baru, pendapatan lain — lihat **P2**) |
| Pemulihan kerugian (uang pemasok, ganti rugi karyawan) | — | Pemulihan Kerugian Stok | **7-1104** (baru, pendapatan lain) |

- Akun baru ditambah lewat seeder COA (idempotent). Akun lama **6-1401 tidak diubah** (tetap dipakai Penyesuaian Stok lama).
- Kategori transaksi baru (`OPN-RUSAK`, `OPN-BASI`, `OPN-HILANG`, `OPN-SUSUT`, `OPN-SAMPEL`, `OPN-SELISIH`, `OPN-LEBIH`, `OPN-PULIH`), `is_system`, tanpa kas. Semua otomatis tampil di Laba Rugi Formal, Laporan → Keuangan, Kas & Transaksi, dan Tutup Buku.
- Baris keuangan opname memakai `referensi_type = 'stok_opname'` → tidak bisa diedit/dihapus dari Kas & Transaksi (sama seperti transaksi order/gaji).
- **Kerugian dibatalkan** (barang diganti pemasok / dipakai lagi) = baris **minus** di akun beban penyebab yang sama, sehingga beban penyebab itu di Laba Rugi menjadi netto.

**Opname vs waste produksi:** waste saat produksi dicatat di hasil nyata Produksi (masuk modal hasil). Opname mencatat kejadian **sesudahnya** (barang jadi rusak/basi/hilang di gudang). Barang yang sama tidak dihitung dua kali.

---

## 5. Stok rusak (ditahan)

Barang Rusak / Basi / Freezer mati keluar dari stok jual (tidak bisa dipakai POS/resep/transfer) ke **stok rusak** per lokasi: barang, jumlah, nilai (harga pokok), asal nomor SO, umur (hari). Kerugian sudah dicatat saat opname disetujui. Menu: Stok & Gudang → **Stok Rusak**.

Tindak lanjut (boleh sebagian jumlahnya):

| Tindakan | Stok | Keuangan |
|---|---|---|
| **Kembali ke pemasok — diganti barang** | masuk lagi ke stok jual (batch harga pokok semula) | kerugian dibatalkan (baris minus di beban penyebabnya) |
| **Kembali ke pemasok — dibayar uang** | — | kas lokasi masuk, **Pemulihan Kerugian Stok** (7-1104) |
| **Kembali ke pemasok — dipotong tagihan** | — | lihat **P9** |
| **Dimusnahkan** | — | tidak ada (kerugian sudah dicatat). **Foto wajib** + **Berita Acara Pemusnahan** (PDF) |
| **Dipakai lagi** | kembali ke stok jual (batch harga pokok semula) | kerugian dibatalkan. Untuk makanan dibatasi, lihat **P8** |

---

## 6. Ganti rugi karyawan (penyebab Hilang)

- Saat menyetujui, penyetuju boleh memilih karyawan yang dibebani (bisa beberapa orang, dibagi nominal). Nilai bawaan = harga pokok bagian Hilang; nilai lain wajib alasan.
- **Tunai** → kas lokasi masuk, **Pemulihan Kerugian Stok** (mengurangi rugi bersih).
- **Potong gaji** → dicicil **1–12 bulan**. Cicilan otomatis masuk kolom baru **"Potongan Ganti Rugi Stok"** di slip Penggajian bulan berikutnya:
  - tidak melebihi gaji bersih (kekurangannya digeser ke bulan berikutnya);
  - **tidak dobel** kalau slip dibuat ulang (cicilan terkait ke slip; slip draft yang dibuat ulang memakai cicilan yang sama);
  - sisa tagihan terlihat di halaman karyawan & Ganti Rugi.
- **Kapan uang dianggap diterima:** saat slip **Dibayar**. Sistem mencatat dua baris tanpa kas: tambahan **Beban Gaji** sebesar potongan, dan **Pemulihan Kerugian Stok** sebesar potongan. Hasilnya Beban Gaji = gaji kotor, kerugian stok terlihat pulih, kas tetap hanya gaji bersih. **Batal Bayar** slip ikut membatalkan keduanya.
- Ganti rugi hanya bisa dibatalkan kalau **belum ada pembayaran** (belum ada tunai/cicilan yang dibayar).

---

## 7. Laporan, dashboard, pengingat, pengaturan

**Berita Acara Stok Opname** (PDF & Excel) — kepala (nomor, lokasi, cakupan, tanggal, penghitung, penyetuju); rincian per barang (sistem, fisik, selisih, harga pokok, nilai, penyebab); ringkasan (dihitung / cocok / kurang / lebih, nilai kurang / lebih / bersih, **akurasi %** — lihat **P13**); lampiran foto; kolom tanda tangan Penghitung / Pemeriksa / Penyetuju. Sebelum disetujui bertanda **SEMENTARA**. Penghitung yang hitung buta tidak bisa mengunduhnya selama opname berjalan.

**Riwayat Stok Opname** per lokasi — status, akurasi, nilai selisih.

**Laporan Kerugian Stok** per periode — per penyebab, per lokasi, per barang; stok rusak & tindak lanjutnya; ganti rugi (dibebankan / diterima / sisa); kerugian yang dipulihkan. Ekspor Excel & PDF.

**Laba Rugi:** akun beban per penyebab tampil sendiri-sendiri secara otomatis (Laba Rugi Formal menampilkan semua akun COA). **Kartu Stok:** penyesuaian dari opname menyebut nomor SO yang bisa diklik.

**Dashboard Stok Opname** — status bulan ini per lokasi (sudah / menunggu persetujuan / sedang dihitung / **belum** = merah); daftar menunggu persetujuan; kerugian bulan ini per penyebab vs bulan lalu; grafik akurasi 6 bulan per lokasi; 10 barang paling sering selisih; stok rusak tertahan (jumlah, nilai, umur); ringkasan ganti rugi.

**Pengingat** — bawaan **H-3 sebelum akhir bulan, jam 08.00** (bisa diatur H-1…H-10, jam bisa diatur, bisa dimatikan). Lokasi yang belum opname bulan itu → notifikasi ke **kepala lokasi** (`kepala_cabang_id`; kalau kosong: user Manajer Cabang lokasi itu) + **Owner**, satu kali per orang per hari. Angka merah di menu Stok Opname selama masa pengingat. Dipicu saat aplikasi dibuka (bagian 1, Tugas terjadwal).

**Pengaturan Umum → Stok Opname** — hitung buta bawaan, batas nilai wajib foto barang hilang, pengingat (nyala/mati, H-berapa, jam). Perubahan dicatat di log aktivitas.

---

## 8. Perbaikan Penyesuaian Stok lama (ikut dikerjakan)

1. Alasan **"Salah Hitung"** dan **"Audit Berkala (Stock Opname)"** yang **menurunkan** stok → sekarang dicatat sebagai **Selisih Persediaan** (6-1406, tanpa kas).
2. Yang **menaikkan** stok dengan dua alasan itu (mis. stok awal) → **dibiarkan seperti sekarang** (tidak dicatat di keuangan). Mohon dikonfirmasi (**P3**).
3. Barang yang sedang ada di opname berjalan **tidak bisa** disesuaikan lewat Penyesuaian Stok / Hapus Stok (supaya selisih tidak terkoreksi dua kali). Lihat **P4**.
4. Penyesuaian Stok alasan rusak/hilang/susut tetap memakai akun lama 6-1401 (tidak diubah).

---

## 9. Yang dipakai ulang & yang baru

**Dipakai ulang:** `StokService` (masuk, kunci & potong batch FIFO, kartu stok), `transaksi_keuangans` + kategori + COA, `CabangAktif`, `NotificationService`, pola badge sidebar, kunci Tutup Buku, layout PDF & style Excel laporan, Penggajian (4.51), seeder panduan/tooltip/Kamus, pola izin additive (`insertOrIgnore`).

**Aturan dalam satu service:** `StokOpnameService` (mulai, hitung, hitung ulang, rincian, ajukan, tolak, setujui, batal, cek hak). Tindak lanjut di `StokRusakService` dan `GantiRugiService`. Controller tipis.

**Tabel baru** (semua lewat migration baru, punya `down()`):

| Tabel | Isi |
|---|---|
| `stok_opnames` | nomor, lokasi, cakupan (+ daftar kategori/barang), hitung buta, status, alasan tolak/batal, waktu & user mulai/ajukan/setujui/batal, ringkasan nilai & akurasi saat disetujui |
| `stok_opname_penghitung` | user penghitung per opname |
| `stok_opname_items` | barang, stok sistem saat dihitung, jumlah fisik, waktu & user hitung, tanda hitung ulang, harga pokok & nilai saat disetujui |
| `stok_opname_rincian` | penyebab, jumlah, foto, catatan, nilai, tautan baris keuangan & kartu stok |
| `stok_rusak` + `stok_rusak_tindakan` | stok ditahan per lokasi & riwayat tindak lanjut (jenis, jumlah, nilai, kas, foto, catatan PO, nomor berita acara) |
| `ganti_rugi` + `ganti_rugi_cicilan` | karyawan dibebani, nilai, cara (tunai / potong gaji), cicilan per bulan, tautan slip gaji |

**Kolom baru:** `penggajians.potongan_ganti_rugi`; `pengaturan_umums`: `opname_hitung_buta`, `opname_batas_foto_hilang`, `opname_pengingat_aktif`, `opname_pengingat_hari`, `opname_pengingat_jam`.

**Menu baru:** Stok & Gudang → **Stok Opname** (daftar + Dashboard), **Stok Rusak**; Laporan → **Laporan Kerugian Stok**; SDM → **Ganti Rugi Stok** (atau tab di Stok Opname). Semua lengkap dengan izin, panduan, tooltip, tombol Cara Pakai, dan Kamus Menu.

---

## 10. Hak akses

| Izin | Isi | Bawaan per role (usulan) |
|---|---|---|
| `stok_opname.view` | Lihat daftar, dashboard, berita acara | Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi |
| `stok_opname.hitung` | Mengisi jumlah fisik | Admin Pusat, Admin Gudang, Manajer Cabang, Kasir, Operator Produksi, Helper |
| `stok_opname.ajukan` | Mulai opname, lihat selisih, minta hitung ulang, isi rincian penyebab, ajukan | Admin Pusat, Admin Gudang, Manajer Cabang |
| `stok_opname.setujui` | Setujui / tolak, pilih karyawan yang dibebani | Admin Pusat (lihat **P7**) |
| `stok_opname.batal` | Batalkan opname yang belum disetujui | Admin Pusat, Admin Gudang, Manajer Cabang |
| `stok_opname.stok_rusak` | Tindak lanjut stok rusak | Admin Pusat, Admin Gudang, Manajer Cabang |
| `stok_opname.ganti_rugi` | Kelola ganti rugi karyawan | Admin Pusat |
| `stok_opname.laporan` | Laporan Kerugian Stok | Admin Pusat, Manajer Cabang |

- Owner = semua (otomatis). Owner bisa mencentang untuk role/role buatan lewat Role & Hak Akses.
- Cakupan = lokasi yang menjadi akses user itu (`CabangAktif`); role tanpa akses semua cabang hanya melihat lokasinya.
- Di production, centangan bawaan hanya diberikan **saat izin pertama kali dibuat** (tidak mengubah centangan Owner yang sudah ada).
- Tanpa izin = 403 (dites).

---

## 11. Tahapan

Deploy **digabung** dengan 4.50 + 4.51 di akhir (sesuai permintaan "sekalian nanti"), dalam satu script deploy baru.

| Tahap | Isi |
|---|---|
| **A** | Tabel inti, izin, `StokOpnameService`, alur mulai → hitung (buta, simpan otomatis, ketik kode) → selisih & hitung ulang → rincian & foto → ajukan / tolak / setujui / batal; akun COA & kategori baru; stok rusak (tabel penampung saja); perbaikan Penyesuaian Stok (bagian 8). |
| **B** | Berita Acara PDF/Excel, Riwayat, Laporan Kerugian Stok, tautan nomor SO di Kartu Stok, cek tampilan di Laba Rugi Formal / Laporan Keuangan / Tutup Buku. |
| **C** | Menu Stok Rusak + tindak lanjut (pemasok, musnahkan + Berita Acara Pemusnahan, dipakai lagi). |
| **D** | Ganti rugi karyawan (tunai / potong gaji + cicilan di Penggajian) + Pengaturan Umum → Stok Opname. |
| **E** | Dashboard Stok Opname + pengingat + angka merah di menu. |

Setiap tahap: feature test aturan & angka uang (termasuk penjualan sesudah barang dihitung, stok habis saat disetujui, satuan desimal, ditolak lalu diajukan ulang, batal, tanpa izin = 403, dua opname bertabrakan); render semua halaman untuk Owner / karyawan berizin / karyawan tanpa izin di lebar desktop & HP; cek skrip halaman; regresi seluruh tes; panduan, tooltip & Kamus diperbarui; commit & push terpisah; laporan singkat.

> Catatan uji tampilan: tampilan HP 390 px dan console dicek lewat render halaman + pemeriksaan skrip seperti di 4.49. Klik sungguhan di browser hanya bisa kalau alat browser tersedia di sesi ini; kalau tidak, akan saya sebutkan di laporan tahap.

---

## 12. Risiko terhadap fitur lama

| Risiko | Penanganan |
|---|---|
| Penjualan/pemakaian sesudah barang dihitung | Diterapkan selisih, bukan menimpa (bagian 2). |
| Stok keburu habis saat disetujui | Persetujuan ditolak utuh dengan pesan jelas (tidak ada perubahan sebagian). |
| Penyesuaian Stok dobel dengan opname | Barang di opname berjalan dikunci dari Penyesuaian/Hapus Stok (**P4**). |
| Tutup Buku sudah dikunci di hari persetujuan | Lihat **P12**. |
| Laba Rugi menjumlah tanpa melihat masuk/keluar | Kelebihan & pemulihan memakai akun pendapatan; pembatalan memakai baris minus (bagian 4). |
| Barang per cabang (4.39) | Opname hanya barang milik lokasi itu (+ data lama tanpa cabang yang punya stok di sana). |
| Kiriman / transfer dalam perjalanan | Tidak tercatat di stok kedua lokasi, jadi tidak memengaruhi selisih. Diterima sesudah dihitung → ikut aman karena memakai selisih. |
| Neraca | Tidak berubah sampai opname disetujui. Setelah disetujui tetap seimbang (batch & laba turun bersamaan). |
| Penggajian | Kolom baru default 0; slip lama tidak berubah. |
| Database belum di-migrate | Badge menu & pengingat mengecek tabel dulu (pola 4.49) supaya tidak error. |

**Hasil tes awal** (sebelum fitur ini): lihat bagian 14.

---

## 13. Pertanyaan yang perlu Bapak putuskan

Saran saya tertulis di setiap pertanyaan. Kalau setuju semua, cukup jawab "setuju semua saran".

| No | Pertanyaan | Saran |
|---|---|---|
| **P1** | Daftar penyebab: Rusak, Basi/Kedaluwarsa, Freezer mati, Hilang, Susut, Sampel/Tester, **Makan karyawan**, Salah catat, Lebih. Perlu "Makan karyawan"? | Ya, masuk Beban Konsumsi (6-1303). Kalau tidak dipakai di D'mentai, dihapus saja. |
| **P2** | Kelebihan stok dicatat ke mana? (a) akun baru **Selisih Lebih Persediaan** (pendapatan lain, 7-1103); (b) akun lama "Koreksi Stok Masuk" (Pendapatan Usaha Lainnya, 4-1199). | (a) — supaya tidak terlihat seperti omzet usaha. |
| **P3** | Penyesuaian Stok alasan Salah Hitung/Audit yang **menaikkan** stok tetap tidak dicatat di keuangan? | Ya, tetap seperti sekarang (sesuai acuan). Catatan: stok naik tanpa laba membuat Neraca selisih sebesar nilainya, jadi untuk stok awal tetap lebih baik lewat PO/pembelian. |
| **P4** | Barang yang sedang di opname berjalan dikunci dari Penyesuaian Stok & Hapus Stok? | Ya. POS, PO, transfer, produksi tetap jalan. |
| **P5** | Barang belum punya barcode. "Scan" pakai apa? (a) ketik/scan **kode item** + Enter (scanner USB/Bluetooth bekerja seperti keyboard); (b) tambah kolom barcode di barang + kamera HP. | (a) dulu. Kolom barcode & kamera bisa menyusul kalau dibutuhkan. |
| **P6** | Satuan selain berat/volume dihitung bulat, padahal 6 barang stoknya sudah pecahan (mis. 12,5 pcs). | Tetap bulat. Selisih pecahan (mis. 0,5) dirinci seperti biasa (Susut / Salah catat). |
| **P7** | Siapa penyetuju bawaan? | Owner + Admin Pusat saja. Manajer Cabang tidak (dia biasanya ikut menghitung/memeriksa). Owner bisa menambah lewat Role & Hak Akses. |
| **P8** | Stok rusak "Dipakai lagi" untuk makanan. | Hanya untuk penyebab **Rusak** (kemasan penyok/pecah, isi aman). Tidak boleh untuk Basi/Kedaluwarsa/Freezer mati — itu hanya bisa Dimusnahkan atau Kembali ke pemasok. |
| **P9** | "Dipotong tagihan" ke pemasok. Sistem mencatat pembayaran PO sebesar total PO. | Dicatat sebagai **Pemulihan Kerugian Stok ke kas yang dipakai membayar PO** + nomor PO wajib. Saat PO dibayar penuh di sistem, kas bersihnya = uang yang benar-benar keluar. Panduan menjelaskan langkahnya. |
| **P10** | Potong gaji: Beban Gaji dicatat gaji kotor + Pemulihan Kerugian (bagian 6)? | Ya. |
| **P11** | Pengingat ke kepala lokasi + Owner; kalau kepala lokasi kosong → Manajer Cabang lokasi itu. | Ya. Bawaan H-3, jam 08.00. |
| **P12** | Kalau hari persetujuan sudah di-Closing Harian (atau periodenya Tutup Buku), keuangan tidak boleh ditulis di tanggal itu. | Persetujuan ditolak dengan pesan "buka kunci Closing hari ini atau setujui besok". Tanggal kerugian = tanggal disetujui. |
| **P13** | Rumus akurasi %. (a) jumlah barang cocok ÷ barang dihitung; (b) berdasar nilai (1 − nilai selisih absolut ÷ nilai stok sistem). | (a) di depan (mudah dipahami), (b) tampil sebagai angka kedua di Berita Acara. |
| **P14** | Saat Ajukan, barang dalam cakupan yang belum dihitung? | Wajib dihitung semua. Barang yang memang tidak bisa dihitung bisa **dikeluarkan dari opname** oleh pemeriksa (tercatat, tidak disesuaikan). |
| **P15** | Ganti rugi karyawan untuk penyebab lain selain Hilang (mis. Rusak karena lalai)? | Hanya Hilang dulu, sesuai acuan. |

---

## 14. Hasil tes awal (sebelum fitur ini)

Regresi `tests/Feature/Tahap25 Tahap5 Tahap6 Tahap7` pada 2026-11-15 (commit `2903459`): **721 lulus, 10 gagal**. Ke-10 kegagalan ini sudah ada sebelum fitur ini dan bukan bagian dari pekerjaan Stok Opname:

- `Tahap25\ItemLegacyRouteTest` ×3 (form Master Barang Lengkap sudah read-only sejak 4.34)
- `Tahap25\MasterProdukJualHttpTest` ×2 (varian, bergantung data seeder)
- `Tahap25\PosImpactTest` ×1 (varian, data seeder)
- `Tahap25\SchemaAndKlasifikasiTest` ×1 (varian, data seeder)
- `Tahap7\KodeKategoriWajibTest` ×2 (form Master Barang Lengkap read-only)
- `Tahap7\RenameJenisMenuMasterBumbuPusatTest` ×1 (form Master Barang Lengkap read-only)

Setiap tahap Stok Opname dianggap lulus regresi kalau yang gagal hanya 10 tes di atas.
