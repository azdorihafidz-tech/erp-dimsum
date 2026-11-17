# Porting dari ERP Dimsum ke BABA: Kamus Menu, Tim IT, Paket & Langganan

Untuk: sesi Claude yang bekerja di workspace **`D:\xampp\htdocs\BABA`** (ERP `baba_erp`, member `baba_api` + `baba_web`).
Sumber: `D:\xampp\htdocs\erp-dimsum` (hanya **dibaca**). Dokumen induk: `erp-dimsum/PORTING_DIMSUM_4.50-4.53.md` — bagian yang dirujuk di sini (mis. "induk §4.1") ada di sana.
Keputusan Owner (2026-11-17): yang sudah dibuat di Dimsum diterapkan juga di BABA, disesuaikan dengan kondisi aplikasinya.

| Bagian BABA | Yang dibawa | Tidak dibawa |
|---|---|---|
| **ERP** (`baba_erp`) | Kamus Menu, akun Tim IT (+ Aktivitas Tim IT), Paket & Langganan, cek bug Dimsum | Stok Opname — sudah ada di BABA (Tahap 7, justru asal fitur Dimsum 4.52) |
| **Member** (`baba_api` + `baba_web`) | Kamus Menu | Stok opname member sudah ada (7f); Tim IT & Paket **tidak** diminta untuk aplikasi member |

---

## 1. Cara kerja wajib

1. **Aturan BABA menang.** Baca `BABA/CLAUDE.md` (workspace + member) dan `baba_erp/CLAUDE.md` dulu: kerja per tahap → uji skenario nyata (HP & desktop; Owner / karyawan / member / superadmin) → commit & push terpisah per repo → laporan per tahap. `db_baba` (member) **tidak boleh** `migrate`/`db:seed`; `DB_STRINGIFY_FETCHES=true` jangan dimatikan.
2. **Langkah 0 — audit, jangan langsung menulis kode** (bagian 5). Jalankan regresi sekali dan catat kegagalan bawaan (ERP: 12 gagal bawaan dimsum).
3. Tulis **rancangan** di `BABA/_rancangan/` (mis. `RANCANGAN-KAMUS-TIMIT-PAKET.md`): kondisi, yang dipakai ulang dari Dimsum, yang harus diubah untuk BABA, risiko, dan pertanyaan yang **benar-benar** perlu diputuskan Owner (bagian 6 sudah final; bagian 7 = pertanyaan terbuka). **Tunjukkan & tunggu persetujuan.**
4. Setelah disetujui: kerjakan semua tahap sampai selesai tanpa menunggu perintah per tahap (tetap uji, commit & lapor per tahap). Panduan + tooltip (ERP) / `panduan/isi.js` (member) ikut diperbarui di tahap yang sama.
5. **Deploy di akhir** dengan cara deploy BABA (hosting punya terminal; lihat catatan hosting di `baba_erp/CLAUDE.md`). Gladi di salinan DB dulu. Kalau memakai skrip sekali pakai, pola induk §1 butir 6.

## 2. Urutan tahap (saran)

| Tahap | Repo | Isi | Sumber Dimsum |
|---|---|---|---|
| **A** | baba_erp | Akun Tim IT di atas Owner + Aktivitas Tim IT (khusus Tim IT) | 4.53 Tahap A + `c5ae530` (induk §4.1) |
| **B** | baba_erp | Paket modul + masa langganan (tenggang → baca-saja) + segel mode lunak | 4.53 Tahap B–D (induk §4.2) |
| **C** | baba_erp | Kamus Menu untuk **semua** menu sidebar ERP + audit menu & bug Dimsum (bagian 4) | 4.50, 4.51 (induk §4.4, §5) |
| **D** | baba_web (+ baba_api bila perlu) | Kamus Menu aplikasi member (bagian 3.4) | pola 4.50, disesuaikan Vue |
| **E** | semua | Aturan permanen di CLAUDE.md (bagian 8) + deploy | — |

Kamus ERP ditulis setelah Tim IT & Paket supaya ikut menjelaskan menu barunya. Kamus member boleh dikerjakan setelah ERP (beberapa fitur member — katalog, pesanan, Toko Saya, opname member — bergantung pada ERP).

## 3. Isi per fitur — yang BERBEDA di BABA

Isi lengkap fitur Dimsum: induk §4. Di bawah ini hanya penyesuaian BABA.

### 3.1 Tim IT (ERP)
- Ikuti induk §4.1 (role `teknisi` "Tim IT", `aksesPenuh()`, `User::pelanggan()`, menu Tim IT, Aktivitas Tim IT khusus Tim IT, Audit Log disaring, akun pertama dari `.env`, 2FA ditunda).
- **Role tambahan BABA**: `pemilik_toko` (toko kemitraan / toko_member). Banyak aturan BABA berbunyi "kecuali Owner & toko mitra" atau "Owner BABA hanya melihat (toko mitra)". Tim IT diperlakukan **seperti Owner BABA**, bukan seperti toko mitra — termasuk tidak boleh mengerjakan opname toko mitra yang memang khusus akun tokonya. Audit setiap cek `RoleUser::Owner` / `'owner'` / `role:owner` (mis. Jadwal Otomatis `role:owner`) dan setiap cek `pemilik_toko`.
- Akun Tim IT tidak boleh muncul di pilihan user mana pun (kepala lokasi, penghitung, penyetuju opname, karyawan untuk ganti rugi, dll.) dan tidak boleh terbawa ke aplikasi member (sambungan `/api/member/*` memakai akun member, bukan user ERP — pastikan tetap begitu).
- `KeuanganBaba::saring` (keuangan toko mitra di luar BABA): pastikan Tim IT melihat keuangan seperti Owner BABA.

### 3.2 Paket & Langganan (ERP)
- Ikuti induk §4.2. **Daftar modul dibuat ulang dari sidebar ERP BABA** (bukan 15 modul Dimsum). Calon modul: Produksi Parfum, Toko Online (produk & harga, pesanan online, belanja ke upline, retur, RO), Kasir/POS, Stok, Stok Opname, Pembelian, Keuangan lanjutan, Akuntansi, Analisis, SDM & Absensi, Penggajian, Penilaian, Aset, Loyalty. Inti (selalu aktif): Dashboard, Cabang/Pemegang Stok, User, Role, Pengaturan Umum, Jadwal Otomatis, Kas & Transaksi (kalau POS/RO butuh kas), Kamus, Panduan, Keamanan.
- **Sambungan aplikasi member** (`routes/api.php`, middleware `aplikasi.member`): `CekPaket` & `ModeBacaSaja` Dimsum hanya untuk web. Putuskan perilaku API member bila modulnya mati / masa langganan habis (pertanyaan 7.1). **Kirim RO (wajib)** tidak boleh berhenti tanpa keputusan Owner.
- **Jadwal Otomatis** (`JadwalOtomatisService`, layar Pengaturan › Jadwal Otomatis): tugas milik modul mati / saat baca-saja dilewati dengan status yang jelas (bukan galat diam-diam); "Jalankan sekarang" mengikuti aturan yang sama. Hosting mematikan `proc_open` — tetap `Schedule::call`.
- Penjaga tes: semua modul dimatikan satu per satu → link sidebar-nya hilang untuk Owner (pola `PaketSidebarGuardTest`); `tests/TestCase::setUp()` memanggil `Paket::lupakan()`.

### 3.3 Kamus Menu (ERP)
- Ikuti induk §4.4 (tabel `kamus_menus`, isi di `database/seeders/kamus/{kelompok}.php`, `KamusMenuSeeder`, tombol Kamus di header, penjaga `KamusMenuTest` tanpa pengecualian).
- File kamus Dimsum (`erp-dimsum/database/seeders/kamus/*.php`) boleh jadi **bahan awal** untuk menu yang sama asalnya, tapi **setiap entri wajib dicek ulang ke kode BABA** — banyak menu sudah berubah (Resep Parfum, produk botol, Pemegang Stok, toko mitra, antrian dimatikan, Jadwal Otomatis, Stok Opname BABA, POS barcode & printer agent). Menu khusus BABA ditulis baru. Pakai istilah BABA: **Upline/Downline, Pemegang Stok, RO (jumlah botol)**.
- Menulis Kamus = mengaudit menu → temuan dilaporkan & diperbaiki dengan persetujuan Owner (bagian 4).

### 3.4 Kamus Menu (aplikasi member — Vue)
Tidak bisa disalin dari Dimsum (Laravel 8 + Vue 3, panduan berbentuk data di frontend). Saran bentuk (sesuaikan saat audit):
- Data di `baba_web/src/kamus/isi.js` (pola `src/panduan/isi.js`): entri per menu — `id`, `route` (sama dengan path router), `menu`, `kelompok`, `peran` ("semua" / "superadmin"; bagian khusus superadmin diberi `peran`), `ringkas`, lalu bagian **Fungsi → Supaya menu ini muncul & bisa dipakai** (akun sudah diaktivasi, jabatan, approval upline, saklar toko, data yang harus ada dulu) **→ Alur → Apa yang terjadi kalau tombol diklik** (RO, stok member, poin, dokumen, notifikasi/WA, data yang terkirim ke ERP) **→ Wajib → Jangan → Menu yang mirip & bedanya**, plus `kata_kunci` untuk pencarian.
- Menu **Kamus Menu** (daftar + cari + detail) dan tombol Kamus di header yang otomatis membuka entri halaman yang sedang dibuka (seperti tombol ❓ Cara Pakai). Bedakan jelas dari Panduan: Panduan = langkah klik; Kamus = fungsi, syarat, dampak.
- Kelompok **Cara Melakukan** untuk pertanyaan sehari-hari member (mis. cara belanja ke upline, cara retur, kenapa menu tidak muncul, kenapa RO belum masuk).
- Isi **ditulis dari kode** (`baba_web` router/views + `baba_api` controller + endpoint ERP yang dipanggil), bukan dari ingatan atau panduan lama.
- Penjaga: skrip/tes yang gagal kalau ada menu di `Sidebar.vue` / router tanpa entri Kamus (cari kerangka tes yang sudah ada di baba_web; kalau belum ada, skrip Node sederhana yang dijalankan saat uji).
- Uji: HP & desktop, superadmin & member biasa — entri/bagian superadmin tidak tampil untuk member.

## 4. Bug Dimsum yang wajib dicek di ERP BABA

ERP BABA disalin dari Dimsum commit **`11fd5b5` (10 Nov 2026, 4.49)** — semua perbaikan sesudahnya **belum** ada, kecuali yang sudah BABA perbaiki sendiri.

| Daftar | Status di BABA (cek ulang saat audit) |
|---|---|
| Induk §5 no. 1–7, 9–14 (edit order dibayar, hapus pelanggan/barang/cabang, item tambahan, Laba Rugi per jenis menu, akses dashboard lewat alamat, Edit Karyawan menurunkan Owner, generate ulang slip, potongan telat, transaksi berulang dobel, Neraca barang terhapus, laporan kas & `?cabang_id`) | kemungkinan besar **masih ada** |
| Induk §5 no. 8 (bayar gaji tidak mengurangi kas) | **sudah diperbaiki BABA** (`JurnalGajiService`, Tahap 5) — jangan dibuat ulang; cek Batal Bayar & slip dihitung ulang |
| Induk §5 no. 15 (Penyesuaian Stok turun tanpa keuangan) | **sudah diperbaiki BABA** (Tahap 7a, SO-SALAH) |
| Password awal karyawan `password123` | **sudah diperbaiki BABA** (Tahap 5, acak) |
| **Riwayat Order mengabaikan cabang di header** untuk role akses semua cabang (Dimsum `f784c3b`, 17 Nov) | kemungkinan **masih ada** — perbaikan: `CabangAktif::untukLaporan()` + kolom Cabang saat "Semua Cabang". Pola serupa (filter cabang hanya untuk role cabang) ada di ±15 controller lain — periksa satu per satu, sebagian memang punya dropdown cabang sendiri |

Isi perbaikan tiap bug: CLAUDE.md Dimsum 4.50 ("Temuan saat audit isi") & 4.51 (A–G), dan commit `8874c10`, `56bc627`, `7b5eab1`, `2903459`, `f784c3b`.

## 5. Yang wajib diaudit (Langkah 0)

ERP:
1. Semua cek Owner, `role:owner`, `pemilik_toko`, toko mitra, `canAccessAllBranches()`; role sistem & role buatan; `Gate::before`.
2. Sidebar lengkap ERP (peta modul paket & daftar Kamus), route web & `routes/api.php` (sambungan member).
3. `JadwalOtomatisService` & `routes/console.php` (tugas mana milik modul mana).
4. Audit Log, log aktivitas, notifikasi, Pengaturan Umum, panduan/tooltip seeder (termasuk `IstilahBabaSeeder` yang harus tetap paling akhir dan menghapus cache yang diubah).
5. Bug bagian 4.
6. Tes: kerangka (`tests/Feature/Baba`), uji layar `shot/*.mjs`, kegagalan bawaan.

Member:
1. Router, `Sidebar.vue`, role/jabatan & `peran`, menu khusus superadmin, `panduan/isi.js` + tombol Cara Pakai, cara uji yang sudah dipakai (puppeteer HP/desktop).
2. Endpoint `baba_api` yang dipanggil tiap menu dan efeknya (RO, stok member, dokumen, approval, WA, kiriman ke ERP).

## 6. Keputusan Owner yang berlaku (sama dengan Dimsum)

- Owner pelanggan **tidak** dijadikan role centangan; penuh di dalam paketnya. Tim IT di atasnya, tersembunyi, **satu akun per orang**, kata sandi min 12 karakter huruf+angka, berbeda per instalasi. Verifikasi dua langkah **ditunda**. "Masuk sebagai user" **ditunda**.
- **Aktivitas Tim IT khusus Tim IT** (Owner tidak melihat); catatan & perubahan oleh akun Tim IT disembunyikan dari Audit Log pelanggan.
- Paket: per modul + preset sebagai tombol cepat (modul tetap bebas diaktifkan di luar paket); modul mati disembunyikan, **data aman**; setelah deploy instalasi = semua modul, tanpa batas.
- Langganan: peringatan **H-14**, tenggang **7 hari**, lalu **baca-saja** (lihat & unduh boleh, simpan/transaksi ditolak; Tim IT tidak terkena). Segel rusak = **mode lunak**.
- Kamus Menu untuk **semua** menu (ERP & member); fitur/menu baru ke depan **wajib** menambah/memperbarui Kamus, sama seperti panduan.

## 7. Pertanyaan terbuka untuk Owner (tanyakan di rancangan, beri saran)

1. **API aplikasi member saat modul ERP mati atau masa langganan habis.** Saran: modul mati → endpoint modul itu menjawab "fitur tidak tersedia" dan menunya disembunyikan di aplikasi member; baca-saja → member masih bisa melihat & mengunduh, tetapi pesanan/retur/opname member ditolak dengan pesan jelas; Kirim RO tetap jalan (data yang sudah terjadi).
2. **Jadwal Otomatis yang wajib** (Kirim RO) saat baca-saja — tetap jalan? (saran: ya, hanya mengirim data yang sudah ada).
3. Kamus member: entri superadmin di aplikasi yang sama (disembunyikan dari member) — saran: ya, seperti Panduan.

## 8. Aturan permanen yang WAJIB ditambahkan ke CLAUDE.md BABA

**`baba_erp/CLAUDE.md`** — bagian "Aturan wajib" butir 3 (fitur/menu baru) ditambah, dan checklist bila ada:
```markdown
- **Kamus Menu** — menu baru DAN perubahan perilaku menu lama (tombol baru, saklar baru di menu lain, izin baru, efek ke stok/kas/laporan/RO berubah) wajib menambah/memperbarui entri di `database/seeders/kamus/{kelompok}.php` (urutan: Fungsi → Supaya menu ini muncul & bisa dipakai → Alur → Apa yang terjadi kalau tombol diklik → Wajib → Jangan → Menu yang mirip & bedanya; isi dari KODE; `route_names` diisi; entri "Cara Melakukan" terkait ikut diperbarui), lalu `KamusMenuSeeder` saat deploy. Penjaga `KamusMenuTest` gagal kalau ada link sidebar tanpa entri — tulis entrinya, jangan menambah pengecualian.
- **Paket modul** — menu/izin/route baru milik modul berbayar WAJIB dipetakan di `App\Support\Paket::IZIN` & `::ROUTE` (tanpa peta = inti, selalu aktif); endpoint baru aplikasi member ikut aturan paket yang disepakati; POST baru yang hanya membaca/mengunduh ditambah ke `ModeBacaSaja::DIKECUALIKAN`; tugas Jadwal Otomatis baru menyebut modulnya.
- **Hak "khusus Owner"** baru memakai `aksesPenuh()` (Owner atau Tim IT), bukan `role === Owner`; daftar/pilihan user untuk pelanggan memakai `User::pelanggan()`.
```

**`BABA/CLAUDE.md`** (workspace/member) — di butir "WAJIB … panduan cara pakainya" tambahkan:
```markdown
- **Kamus Menu (WAJIB, sama seperti panduan):** menu baru atau perubahan perilaku menu → perbarui juga `baba_web/src/kamus/isi.js` dalam pekerjaan yang sama (fungsi, syarat supaya menu muncul, alur, apa yang terjadi kalau tombol diklik termasuk data yang terkirim ke ERP, wajib, jangan, menu yang mirip). `route` = path router; bagian khusus superadmin diberi `peran: "superadmin"`. Penjaga Kamus harus tetap lulus.
```

## 9. Pelajaran teknis dari Dimsum

Lihat induk §9, ditambah:
- **Jam server hosting bisa melompat** (Dimsum 16 Nov 2026: +7 jam selama ±40 menit → transaksi tercatat tanggal besok; diperbaiki dengan skrip sekali pakai, commit `51f4cb7`). Kalau ada transaksi bertanggal aneh: urutkan menurut ID — baris yang bergeser terlihat "diapit" baris berjam normal. Laporkan ke hosting (sinkron NTP).
- Saat menghapus/mengganti isi panduan & tooltip lewat `DB::table`, cache tidak terhapus otomatis (BABA sudah tahu ini dari `IstilahBabaSeeder`) — berlaku juga untuk Kamus (`Cache::forget('kamus_menu:semua:v1')` di seeder).
