# Porting dari ERP Dimsum: Kamus Menu, Tim IT + Paket & Langganan, Stok Opname (4.50–4.53)

Untuk: sesi Claude yang bekerja di **erp-coffeeshop** atau **erp-manajemenberkahmulyo**.
Sumber: `D:\xampp\htdocs\erp-dimsum` (boleh **dibaca** untuk contoh kode; jangan diubah dari sesi proyek lain).
Keputusan Owner (2026-11-16): semua yang dibuat di Dimsum dari Kamus Menu sampai sekarang diterapkan juga di Coffeeshop dan Berkah Mulyo, **disesuaikan dengan kondisi aplikasinya**, dengan keputusan bisnis yang sama seperti di Dimsum (bagian 7).

---

## 1. Cara kerja wajib

1. **Baca CLAUDE.md proyek tujuan dulu.** Aturan proyek tujuan menang kalau bertentangan dengan dokumen ini.
2. **Langkah 0 — audit, jangan langsung menulis kode.** Pelajari kondisi proyek (bagian 6), jalankan seluruh tes sekali dan catat yang **sudah gagal sejak awal**.
3. Tulis **rancangan** (file di root proyek, mis. `RANCANGAN_PORTING_DIMSUM.md`): kondisi yang ditemukan, yang dipakai ulang, yang baru, yang harus diubah dari versi Dimsum, risiko, dan pertanyaan yang **benar-benar** perlu diputuskan Owner (keputusan di bagian 7 sudah final, jangan ditanyakan ulang kecuali kondisinya berbeda). **Tunjukkan & tunggu persetujuan.**
4. Setelah disetujui: kerjakan semua tahap sampai selesai, **tiap tahap**: feature test (aturan & angka uang), uji render halaman untuk Owner / karyawan berizin / tanpa izin + pemeriksaan skrip, regresi seluruh tes, panduan + tooltip + Kamus diperbarui, commit & push terpisah, laporan singkat bahasa Indonesia.
5. **Data:** hanya migration baru (punya `down()`), seeder idempotent, izin baru ADDITIVE (centangan role bawaan hanya saat izin pertama kali dibuat). **Dilarang** `migrate:fresh` / `migrate:refresh` / `db:seed` penuh di database yang dipakai. Tes memakai DB tes / transaksi yang dibatalkan.
6. **Deploy di akhir**, satu skrip gabungan sekali pakai (pola `public/deploy-452.php` Dimsum: konfirmasi `?confirm=…`, lock di `storage/`, laporan baca-saja, hapus file setelah jalan). Gladi dulu di salinan DB (mundurkan migration baru). Instruksi deploy = link langsung tiap instalasi.
7. **Berkah Mulyo = produksi dengan data nyata**: backup DB sebelum deploy, deploy di luar jam kerja, gladi wajib.

## 2. Urutan tahap (saran yang disetujui Owner)

| Tahap | Isi | Sumber Dimsum |
|---|---|---|
| **A** | Akun Tim IT di atas Owner + Aktivitas Tim IT (khusus Tim IT) | 4.53 Tahap A + perubahan `c5ae530` |
| **B** | Paket modul per instalasi + Masa langganan (tenggang → baca-saja) + segel mode lunak | 4.53 Tahap B–D |
| **C** | Stok Opname lengkap (+ Stok Rusak, Laporan Kerugian, Ganti Rugi, Dashboard, Pengingat) | 4.52 Tahap A–E |
| **D** | Kamus Menu untuk **semua** menu sidebar + audit menu (temukan & perbaiki bug seperti 4.50/4.51) | 4.50, 4.51 |
| **E** | Aturan permanen di CLAUDE.md proyek tujuan (bagian 8) + skrip deploy gabungan | — |

Kamus ditulis terakhir supaya ikut menjelaskan menu Tim IT, Paket, dan Stok Opname yang baru. Setiap tahap tetap menambah panduan & tooltip untuk menunya sendiri.

## 3. Peta sumber di Dimsum

Baca bagian **4.50, 4.51, 4.52, 4.53** di `erp-dimsum/CLAUDE.md` (ringkasan teknis lengkap) dan rancangan `RANCANGAN_STOK_OPNAME.md`, `RANCANGAN_PAKET_LANGGANAN.md`. Commit (urut): Kamus `323bb1e` `8874c10` `56bc627` `7b5eab1` `4d31237` `c008e1c` `2903459` · Stok Opname `6f8015f` `1b2b3ad` `5d09e36` `ca3afa5` `3e1ade8` · Tim IT & Paket `79d87d4` `4005416` `24ccc26` `c5ae530`. Lihat isi: `git -C D:\xampp\htdocs\erp-dimsum show <commit> --stat`.

| Fitur | File utama di Dimsum |
|---|---|
| Kamus Menu | `database/migrations/2026_11_15_100001_create_kamus_menus_table.php`, `app/Models/KamusMenu.php`, `app/Http/Controllers/KamusMenuController.php`, `resources/views/kamus-menu/*`, `resources/views/components/kamus-menu-tombol.blade.php`, `database/seeders/KamusMenuSeeder.php`, `database/seeders/kamus/*.php` (isi per kelompok), `tests/Feature/Tahap7/KamusMenuTest.php` (penjaga sidebar) |
| Tim IT | `app/Enums/RoleUser.php` (Teknisi), `app/Models/User.php` (`isTeknisi`, `aksesPenuh`, `scopePelanggan`, hasPermission), `app/Http/Middleware/RoleMiddleware.php`, `app/Http/Middleware/CatatAktivitasTeknisi.php`, `app/Http/Controllers/TimItController.php`, `AktivitasTimItController.php`, `AuditLogController.php` (`sembunyikanTimIt`), `app/Support/Roles.php`, `resources/views/tim-it/*`, `database/seeders/kamus/tim-it.php`, `tests/Feature/Tahap7/TimItTest.php` |
| Paket & Langganan | `app/Support/Paket.php`, `app/Http/Middleware/CekPaket.php`, `ModeBacaSaja.php`, `app/Http/Controllers/PaketController.php`, `database/migrations/2026_11_18_100001_create_lisensi_table.php`, `resources/views/paket/*`, `resources/views/components/pita-langganan.blade.php`, `database/seeders/PaketLanggananSeeder.php`, tes `PaketModulTest`, `PaketSidebarGuardTest`, `PaketLanggananTest`, `tests/TestCase.php` (`Paket::lupakan()`) |
| Stok Opname | `app/Services/StokOpnameService.php`, `StokRusakService.php`, `GantiRugiService.php`, `LaporanKerugianStokService.php`, `StokOpnameDashboardService.php`, `StokOpnamePengingat.php`, tambahan di `app/Services/StokService.php` (`pastikanTidakDiopname`, `kurangiPenyesuaian`, `tambahPenyesuaian`, `hargaPokokRataRata`, `perkiraanNilaiKeluar`), `app/Support/StokOpnameAkun.php`, `app/Enums/PenyebabSelisihStok.php`, model `StokOpname*`, `StokRusak*`, `GantiRugi*`, migration `2026_11_17_*`, view `stok-opname/*`, `stok-rusak/*`, `ganti-rugi/*`, `laporan/kerugian-stok/*`, `database/seeders/StokOpnameSeeder.php`, tes `StokOpname*Test` + `StokOpnameTestCase` |
| Perbaikan audit 4.50/4.51 | lihat CLAUDE.md Dimsum 4.50 ("Temuan saat audit isi") & 4.51 (A–G) |

## 4. Isi fitur (ringkas — detail di CLAUDE.md Dimsum)

### 4.1 Tim IT (4.53 Tahap A)
- Role sistem `teknisi` (label "Tim IT"): akses semua cabang, semua izin **di dalam paket**, plus menu **Tim IT** & **Paket & Langganan**.
- **Satu fungsi `aksesPenuh()` = Owner atau Teknisi** menggantikan semua pengecekan "khusus Owner" (Gate::before, hasPermission, middleware `role:owner`, menu Role & Hak Akses / Pengaturan Umum, dll). Audit semua `RoleUser::Owner` / `'owner'` di proyek tujuan; yang tetap Owner saja: penerima notifikasi Owner, penjaga "Owner terakhir".
- **Tersembunyi dari pelanggan**: tidak ada di Manajemen User (+404), Role & Hak Akses, pilihan role, pilihan user (kepala cabang, penghitung, penyetuju, dsb). Owner tidak bisa mengubah/menghapus akun Tim IT.
- Menu **Tim IT** (khusus Tim IT): tambah / ganti kata sandi / aktif-nonaktif; kata sandi min 12 huruf+angka; tidak bisa menonaktifkan diri sendiri / akun aktif terakhir.
- **Aktivitas Tim IT — KHUSUS Tim IT** (Owner tidak melihat): middleware mencatat setiap non-GET oleh Tim IT (route, url, method, IP, status — **isi formulir tidak disimpan**) + listener Login. **Audit Log** menyembunyikan log Tim IT dan semua aktivitas ber-causer akun Tim IT dari user pelanggan (index, daftar log, pilihan user, detail → 404).
- Akun Tim IT pertama dibuat skrip deploy dari `.env` (`TEKNISI_NAMA`, `TEKNISI_EMAIL`, `TEKNISI_PASSWORD`) — dibaca langsung dari file `.env` (`Dotenv::parse`) karena config bisa ter-cache; kata sandi tidak dicetak.
- **Verifikasi dua langkah DITUNDA** (keputusan Owner).

### 4.2 Paket & Langganan (4.53 Tahap B–D)
- Tabel `lisensi` (1 baris): modul JSON, aktif_sampai, masa_tenggang_hari, segel (HMAC `APP_KEY`), diubah_by, peringatan_segel_at. **Instalasi lama = semua modul, tanpa batas** → tidak ada yang berubah setelah deploy.
- `App\Support\Paket` satu titik pusat: daftar MODUL + ketergantungan, PRESET (Dasar/Menengah/Lengkap = tombol cepat, isi lain "Kustom"), peta IZIN & ROUTE (pola nama → modul; **tidak terpetakan = inti, selalu aktif**).
- Modul mati ditolak untuk **semua** user termasuk Owner & Tim IT: Gate::before + hasPermission → menu/tombol/Kamus hilang otomatis; middleware CekPaket → halaman "tidak termasuk paket"; Role & Hak Akses menyembunyikan izinnya **dan mempertahankan centangan tersembunyi saat disimpan**; pekerjaan otomatis modul itu berhenti. Data tidak dihapus.
- Masa langganan: pita kuning H-14 (Owner/Tim IT) → masa tenggang (bawaan 7 hari, pita merah semua user, masih normal) → **baca-saja** (lihat & unduh boleh; simpan/ubah/hapus & transaksi ditolak; JSON 423). Tim IT tidak terkena. Pengecualian POST yang hanya membaca (export, pratinjau, logout, kata sandi sendiri, ganti cabang, notifikasi).
- Segel rusak (isi diubah langsung di DB) = **mode lunak**: tetap jalan, pita merah + notifikasi untuk Tim IT sekali sehari.
- Penjaga: tes yang mematikan tiap modul dan memastikan link sidebar-nya hilang untuk Owner. `tests/TestCase::setUp()` memanggil `Paket::lupakan()` (status disimpan statis).
- **Daftar modul HARUS dibuat ulang sesuai menu proyek tujuan** (Dimsum: 15 modul — Penjualan & POS, Antrian, Loyalty, Stok, Produksi, Kirim ke Cabang & Retur, Stok Opname, Pembelian, Keuangan Lanjutan, Akuntansi, Analisis Bisnis, SDM & Absensi, Penggajian, Penilaian 360°, Aset; inti: Dashboard, Cabang, User, Role, Pengaturan Umum, Kas & Transaksi + Kelola Kas, Kamus, Panduan, Keamanan). Kalau POS butuh kas untuk mencatat uang, Kas & Transaksi tetap inti.

### 4.3 Stok Opname (4.52)
- Prinsip uang: kerugian = **beban tanpa kas**, nilai = **harga pokok FIFO**; stok & keuangan baru berubah saat **disetujui**; yang diterapkan = **selisih** (fisik − stok sistem SAAT barang dihitung) → lokasi tetap boleh berjualan selama opname.
- Alur: mulai (lokasi, cakupan semua/kategori/barang, penghitung; `SO-YYYYMM-NNNN`) → hitung (simpan otomatis, ketik/scan kode + Enter = +1; berat/volume boleh desimal, lainnya bulat) → hitung buta (bawaan nyala) → periksa selisih, minta hitung ulang, keluarkan barang (alasan) → rincikan penyebab (dipecah, jumlah = selisih; foto wajib Rusak/Basi/Freezer mati, Hilang kalau ≥ batas bawaan Rp 200.000; 0 = semua) → ajukan → setujui/tolak (alasan) → batal sebelum disetujui. Penghitung tidak boleh menyetujui opname yang ia hitung (kecuali Owner/Tim IT). Satu barang hanya di satu opname berjalan per lokasi; barang di opname berjalan dikunci dari Penyesuaian Stok & Hapus Stok.
- Persetujuan: kunci baris stok, cek SEMUA kekurangan dulu (stok keburu terjual → tolak utuh), tolak kalau hari itu sudah Closing/Tutup Buku, 1 baris keuangan tanpa kas per penyebab, kartu stok tipe Penyesuaian + referensi nomor SO. Lebih → batch baru harga pokok rata-rata.
- Penyebab & akun: Rusak / Freezer mati → Kerugian Stok–Rusak; Basi/Kedaluwarsa → –Basi; Hilang → –Hilang; Susut → Penyusutan Stok; Sampel → Promosi & Sampel; Makan karyawan → Beban Konsumsi; Salah catat → Selisih Persediaan; Lebih → **Selisih Lebih Persediaan (pendapatan lain)**; uang kembali → **Pemulihan Kerugian Stok (pendapatan lain)**; pembatalan kerugian = **baris minus di akun beban yang sama**. ⚠️ Di Dimsum Laba Rugi Formal menjumlah per akun **tanpa melihat masuk/keluar** — cek perilaku laporan proyek tujuan sebelum memilih akun.
- Stok Rusak (ditahan, bukan stok jual): kembali ke pemasok (diganti barang → stok masuk lagi + kerugian dibatalkan; dibayar uang → kas masuk; dipotong tagihan → nomor PO wajib, uang masuk ke kas yang dipakai membayar PO), dimusnahkan (foto wajib + Berita Acara Pemusnahan PDF), dipakai lagi (**hanya penyebab Rusak**).
- Ganti rugi karyawan (Hilang): dibebankan dari halaman opname yang sudah disetujui; nilai bawaan = harga pokok (nilai lain wajib alasan); tunai → kas masuk; potong gaji 1–12 bulan otomatis di slip (maks gaji bersih, tidak dobel saat slip dihitung ulang); saat slip **dibayar**: tambahan Beban Gaji (gaji kotor) + Pemulihan Kerugian Stok, tanpa kas; Batal Bayar membatalkan. **Butuh pembayaran gaji yang mengurangi kas** (Dimsum 4.51) — kalau proyek tujuan belum punya, rancang dulu.
- Laporan: Berita Acara PDF/Excel (SEMENTARA sebelum disetujui, foto, tanda tangan; penghitung buta tidak bisa mengunduh selama berjalan), Laporan Kerugian Stok, tautan nomor SO di Kartu Stok & Kas, Dashboard opname (status per lokasi, akurasi 6 bulan + tabel, barang paling sering selisih, stok rusak, ganti rugi), pengingat H-3 jam 08.00 ke kepala lokasi (kosong → manajer cabang) + Owner sekali per orang per hari (dicek dari tabel notifikasi), angka merah di menu. Pengaturan Umum → kartu Stok Opname (hitung buta, batas foto, pengingat; log aktivitas).
- Perbaikan Penyesuaian Stok: alasan salah hitung/audit yang **menurunkan** stok → Selisih Persediaan; yang menaikkan tetap tanpa keuangan.

### 4.4 Kamus Menu (4.50)
- Tabel `kamus_menus`; isi **dimiliki kode** di `database/seeders/kamus/{kelompok}.php` (seeder menghapus entri yang tidak ada di file). Urutan bagian tiap entri: **Fungsi → Supaya menu ini muncul & bisa dipakai** (izin, saklar di menu LAIN, data yang harus ada dulu) **→ Alur → Apa yang terjadi kalau tombol diklik** (tabel stok/kas/laporan) **→ Wajib → Jangan → Menu yang mirip & bedanya**. Isi ditulis **dari kode, bukan ingatan**.
- `izin` = daftar "salah satu cukup" + pseudo `@owner`, `@semua_cabang`, `@teknisi`; `route_names` (pola) untuk tombol Kamus di header; entri tidak tampil kalau user tidak boleh membuka menunya atau menunya di luar paket. Pencarian kata sehari-hari; kelompok **Cara Melakukan** (pindah barang, tambah/kurangi stok, batal transaksi, melihat untung, "Kenapa menu/fitur tidak ada?", dst).
- Penjaga `KamusMenuTest`: setiap `route('…')` di sidebar wajib punya entri (tanpa daftar pengecualian).
- **Menulis Kamus = mengaudit menu.** Di Dimsum audit ini menemukan banyak bug nyata (bagian 5). Laporkan & perbaiki temuan dengan persetujuan Owner.

## 5. Daftar periksa bug yang ditemukan di Dimsum (cek apakah ada juga di proyek tujuan)

| No | Temuan di Dimsum | Perbaikan Dimsum |
|---|---|---|
| 1 | Edit order yang sudah dibayar menimpa jumlah transaksi kas tanpa mengubah saldo kas/setoran | Diskon & metode bayar hanya untuk order Pending; observer hanya sinkron keterangan |
| 2 | Hapus pelanggan ikut menghapus order-nya | Hanya pelanggan; saklar Aktif |
| 3 | Hapus barang ikut menghapus riwayat order/PO/stok, foto dihapus permanen | Ditolak kalau masih ada stok/dipakai resep/PO aktif; riwayat tetap |
| 4 | Item tambahan tidak memotong stok di POS | Aturan potong stok bersama |
| 5 | Laporan Laba Rugi "per jenis menu" selalu kosong | Kolom jenis menu di produk + snapshot di order |
| 6 | Dashboard semua cabang / gudang bisa dibuka lewat alamat langsung | Cek akses di controller |
| 7 | Form Edit Karyawan diam-diam menurunkan role Owner; email/role akun bisa diubah tanpa izin user | Butuh izin user.*; akun Owner tidak bisa diubah dari form Karyawan |
| 8 | Bayar gaji tidak mengurangi kas | Bayar → pilih kas, transaksi Gaji; Batal Bayar |
| 9 | Generate ulang slip menimpa slip Disetujui/Dibayar | Dilewati + disebutkan |
| 10 | Potongan telat per menit tidak dipakai | Dipakai di slip |
| 11 | Transaksi berulang mengabaikan frekuensi, bisa dobel | Jadwal per frekuensi, 1 transaksi per periode |
| 12 | Hapus cabang menyembunyikan semua data cabang itu | Ditolak kalau cabang sudah beroperasi |
| 13 | Neraca menghitung stok barang yang sudah dihapus | Dikecualikan |
| 14 | Laporan kas menjumlah transfer internal/setoran/saldo awal sebagai pemasukan/pengeluaran; filter `?cabang_id` bocor ke cabang lain | Transaksi internal dikecualikan + info; filter cabang dipaksa |
| 15 | Penyesuaian Stok salah hitung/audit yang menurunkan stok tidak tercatat di keuangan | Selisih Persediaan (bagian 4.3) |

## 6. Yang wajib diaudit di proyek tujuan (Langkah 0)

1. Model stok (FIFO batch? kartu stok + referensi? satuan & desimal, lokasi/cabang, data barang per cabang atau global, barcode, kedaluwarsa per batch).
2. Fitur yang bersinggungan: penyesuaian stok, produksi/waste, retur, transfer, POS, closing/tutup buku (Coffeeshop punya Closing Periode sendiri).
3. Keuangan: COA, cara transaksi keuangan dibuat (jurnal berpasangan atau satu baris per kejadian), kas per cabang, Laba Rugi & Neraca (cara menjumlah per akun!), persediaan di neraca.
4. Hak akses: role sistem & role buatan, `Gate::before`, semua cek Owner, middleware role, cabang per user.
5. Penggajian: kolom potongan, apakah bayar gaji mengurangi kas.
6. Notifikasi, log aktivitas, Audit Log, Pengaturan Umum, panduan/tooltip seeder, laporan PDF/Excel.
7. Tugas terjadwal: hosting tanpa terminal? `proc_open` dimatikan? → pakai pemicu saat aplikasi dibuka + `Schedule::call()`.
8. Sidebar lengkap (untuk peta modul paket & Kamus).
9. Tes: kerangka, DB tes, kegagalan awal.

Catatan per proyek:
- **Coffeeshop**: beberapa fitur Dimsum berasal dari Coffeeshop (Produksi, Kiriman, Retur, Label, APK) dan sebaliknya — cek dulu apa yang sudah sama. Ada Closing Periode milik Coffeeshop sendiri (sesuaikan penolakan "hari sudah di-closing").
- **Berkah Mulyo**: aplikasi asal (jasa giling daging), produksi dengan data nyata. Banyak perbaikan Dimsum (4.37–4.51) kemungkinan **belum** ada — jangan diasumsikan; masukkan ke rancangan sebagai prasyarat yang perlu dikerjakan/disederhanakan. Hati-hati dengan data lama dan transaksi nyata.

## 7. Keputusan Owner yang berlaku juga di proyek tujuan

- Owner pelanggan **tidak** dijadikan role centangan; penuh di dalam paketnya. Tim IT di atasnya, tersembunyi, satu akun per orang.
- **Aktivitas Tim IT khusus Tim IT** (Owner tidak melihat), catatan Tim IT disembunyikan dari Audit Log pelanggan.
- Verifikasi dua langkah ditunda; kata sandi Tim IT min 12 karakter, berbeda per instalasi; batas salah login tetap aktif.
- Paket: per modul + preset sebagai tombol cepat (modul bebas diaktifkan di luar paket); modul mati disembunyikan, data aman; setelah deploy semua instalasi = semua modul tanpa batas.
- Langganan: peringatan H-14, tenggang 7 hari, lalu **baca-saja**; segel rusak = **mode lunak**.
- Stok Opname: semua saran P1–P15 Dimsum (lihat `erp-dimsum/RANCANGAN_STOK_OPNAME.md` bagian 13) — penyetuju bawaan Owner + Admin Pusat (sesuaikan nama role proyek), "dipakai lagi" hanya Rusak, potong tagihan = uang ke kas yang membayar PO, persetujuan ditolak kalau hari sudah di-closing, akurasi = barang cocok ÷ dihitung (+ akurasi nilai), barang belum dihitung wajib dihitung atau dikeluarkan.
- Kamus Menu untuk semua menu; fitur/menu baru ke depan **wajib** menambah/memperbarui Kamus (bagian 8).

## 8. Aturan permanen yang WAJIB ditambahkan ke CLAUDE.md proyek tujuan

Tambahkan (sesuaikan nama file/kelas dengan proyek itu) ke bagian aturan kerja "fitur/menu baru wajib…" (setara Dimsum 3.6) dan checklist sebelum mulai fitur:

```markdown
6. **Kamus Menu** — tambah/ubah entri di `database/seeders/kamus/{kelompok}.php`, jalankan `KamusMenuSeeder` saat deploy. Berlaku untuk **menu baru** DAN **perubahan perilaku menu lama** (tombol baru, saklar baru di menu lain, izin baru, efek ke stok/kas/laporan berubah). Isi dari KODE, urutan: Fungsi → Supaya menu ini muncul & bisa dipakai → Alur → Apa yang terjadi kalau tombol diklik → Wajib → Jangan → Menu yang mirip & bedanya. Isi `route_names`. Perbarui entri "Cara Melakukan" terkait. Penjaga `KamusMenuTest` gagal kalau ada link sidebar tanpa entri — tulis entrinya, jangan menambah pengecualian.
7. **Paket modul** — menu/izin baru milik modul berbayar WAJIB dipetakan di `App\Support\Paket::IZIN` & `::ROUTE` (tanpa peta = inti, selalu aktif); penjaga `PaketSidebarGuardTest`. POST baru yang hanya membaca/mengunduh ditambah ke `ModeBacaSaja::DIKECUALIKAN`.
8. **Hak "khusus Owner"** baru memakai `aksesPenuh()` (Owner atau Tim IT), bukan `role === Owner`. Daftar/pilihan user untuk pelanggan memakai `User::pelanggan()` (akun Tim IT tersembunyi).
```

Checklist sebelum mulai fitur baru (tambahkan):
```markdown
- [ ] Tambah/perbarui Kamus Menu (syarat di menu lain & efek tiap tombol)
- [ ] Petakan menu/izin baru ke modul di App\Support\Paket kalau milik modul berbayar
```

## 9. Pelajaran teknis dari Dimsum (hindari)

- `abort(403)` di dalam service adalah `RuntimeException` → controller yang `catch (RuntimeException)` harus meneruskan `HttpExceptionInterface`.
- Status paket disimpan statis → reset di `TestCase::setUp()` dan setelah menyimpan.
- Skrip deploy yang membaca `.env`: baca file langsung (`Dotenv::parse`), karena `env()` kosong kalau config ter-cache. Gladi deploy yang menjalankan `config:cache` di lokal → jalankan `config:clear` sesudahnya.
- `APP_LOCALE=en` → nama bulan `translatedFormat()` keluar bahasa Inggris; pakai daftar nama bulan sendiri.
- Ekspresi kompleks di `@json(...)` Blade bisa salah parse → hitung dulu di `@php`.
- Jangan ada `<form>` bersarang (Dimsum punya penjaga tes untuk ini).
- Uji pengiriman "sekali per hari" memakai tanggal nyata (`travelTo`), karena baris notifikasi tercatat dengan waktu sekarang.
