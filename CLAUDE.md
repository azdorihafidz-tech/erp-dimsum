# CLAUDE.md — ERP D'mentai

> **Untuk Claude Code**: File ini adalah **single source of truth** untuk seluruh project. WAJIB dibaca sebelum eksekusi apapun.
> Isinya: keputusan bisnis, temuan audit, aturan teknis, dan filosofi kerja.

**Versi**: 4.4  
**Update terakhir**: 2026-09-21  
**Status**: 🎉 **PROJECT LIVE DI PRODUCTION** (https://erpdimsum.azwacore.com) — Tahap 1-7 SELESAI SEMUA + rangkaian bug fix & improvement lintas sesi (lihat section 4 utk detail lengkap tiap item): Bug Fix Ronde 2, Rename Jenis Menu/Master Bumbu Pusat, Fitur Import dari Bumbu Pusat, Rename Label & Hapus Gojek/Grab, Fix Foto Produk Production, UI Preview Harga Master/Subtotal Resep (Ronde 3-5), Auto-isi Satuan Master Bumbu Pusat, Fix ENUM Kategori Saldo Awal, Fix Label Kode Kategori Wajib, Default Basis Program Loyalty ke Rp, Widget Total Pembelian Pelanggan, Fix Export Excel Laporan Keuangan. **Sprint 3 Batch 1 SELESAI SEMUA** (5 menu Prioritas 1 — Penjualan, Setoran Kasir, Setoran Harian/Rekap, Stok 3 sub-view, Laba Rugi Produksi — lihat [[4.24]], [[4.25]]) — Sprint 2 (konversi satuan resep) DIDEFER [[12.12]], Sprint 3 Batch 2/3 (17 menu Laporan Prioritas 2/3) + TODO tracking stock movement [[12.14]] MENYUSUL [[12.13]].

---

## 1. GAMBARAN UMUM PROJECT

### 1.1 Identitas Brand
- **Nama Brand**: **D'mentai**
- **Tagline**: **Dimsum & Gyoza**
- **Jenis Bisnis**: Retail food (dimsum & gyoza)
- **Sistem**: ERP (Enterprise Resource Planning) — POS + inventory + HR + keuangan

### 1.2 Asal Project
- **Base**: hasil copy penuh dari `erp-manajemenberkahmulyo` (Laravel 12) — ERP untuk jasa giling daging
- **Tanggal fork**: September 2026
- **Alasan fork**: bisnis beda total, tapi 90% infrastruktur bisa reuse

### 1.3 Struktur Bisnis Saat Ini
- **1 HO / Gudang Pusat** (di rumah owner)
- **5 Outlet retail**
- **Total 6 lokasi** di sistem

### 1.4 Prinsip Fleksibilitas (FILOSOFI UTAMA!)
Meski saat ini ada 5 outlet, sistem **HARUS fleksibel** untuk:
- Jumlah outlet bisa nambah/berkurang tanpa ganti kode
- Lokasi outlet: 1 kota / lintas kota / lintas provinsi
- Harga per outlet: bisa sama semua atau beda-beda (config-driven)
- Jenis produk per outlet: bisa beda (config per outlet)
- Varian produk: N-dimensi, opsional per produk
- Stok varian: bisa terpisah atau ikut induk (config per produk)

**Aturan emas: kalau bisa jadi config, JANGAN hardcode.**

---

## 2. TECH STACK & LINGKUNGAN

### 2.1 Stack
| Item | Detail |
|------|--------|
| Framework | Laravel 12 (bootstrap/app.php style, no Kernel.php) |
| PHP | 8.2+ |
| Database | MySQL (via XAMPP) |
| Frontend | Bootstrap 5.3.3 (CDN) + Select2 + SweetAlert2 + Chart.js + Bootstrap Icons + jQuery |
| Permission | 100% custom (BUKAN spatie/laravel-permission) |
| PDF | barryvdh/laravel-dompdf ^3.1 |
| Excel | maatwebsite/excel ^3.1 |
| Activity Log | spatie/laravel-activitylog ^4.12 |
| Backup | spatie/laravel-backup ^9.3 |
| PWA | silviolleite/laravelpwa ^2.0 |
| Realtime | pusher/pusher-php-server ^7.2 |

### 2.2 Lingkungan Lokal
| Item | Detail |
|------|--------|
| Path folder | `D:\xampp\htdocs\erp-dimsum` |
| Nama database | `erp_dimsum` |
| Port dev | `php artisan serve --port=8001` |
| URL akses | `http://localhost:8001` |

### 2.3 Project Lain di Environment (JANGAN DISENTUH)
| Project | Folder | Database | Port | Status |
|---------|--------|----------|------|--------|
| erp-manajemenberkahmulyo (ASLI) | `erp-manajemenberkahmulyo` | `erp_berkahmulyo` | 8000 | 🔴 Production, HARAM disentuh |
| erp-dimsum (INI) | `erp-dimsum` | `erp_dimsum` | 8001 | 🟢 Aktif dikerjakan |
| erp-coffeeshop (lain) | `erp-coffeeshop` | `erp_coffeeshop` | 8002 | 🟡 Dikerjakan di chat lain |

---

## 3. ATURAN KERJA WAJIB (JANGAN DILANGGAR)

### 🔴 3.1 JANGAN pernah sentuh folder ini:
- `D:\xampp\htdocs\erp-manajemenberkahmulyo` — project produksi Berkah Mulyo
- `D:\xampp\htdocs\erp-coffeeshop` — project coffeeshop terpisah
- Database `erp_berkahmulyo` — data real yang lagi dipakai

Semua modifikasi HANYA di folder `erp-dimsum` dan database `erp_dimsum`.

### 🔴 3.2 Test SETIAP perubahan
Setelah edit kode, WAJIB:
1. Cek error di `storage/logs/laravel.log`
2. Jalankan `php artisan config:clear` dan `php artisan view:clear`
3. Coba akses menu terkait di browser
4. Kalau ada error, FIX DULU sebelum lanjut fitur lain

### 🔴 3.3 Reuse dulu, bikin baru terakhir
Sebelum bikin controller/model/view baru, **cek dulu** apakah pola serupa sudah ada:
- Cek folder: `app/Http/Controllers`, `app/Models`, `resources/views`
- Kalau ada pola serupa, IKUTI pola itu (naming, struktur folder, style code)
- Jangan bikin pola baru kalau tidak perlu

### 🔴 3.4 Filter Cabang WAJIB Manual (SECURITY-CRITICAL!)
**Temuan audit ronde 2**: `CabangScope` DORMANT (tidak pernah aktif). Ke-62 pemanggilan `withoutGlobalScope(CabangScope::class)` di codebase adalah no-op — efek nyatanya cuma matiin `SoftDeletingScope`.

**Konsekuensi**: Filter cabang HARUS ditulis manual di setiap query.

**Contoh yang BENAR**:
```php
$orders = Order::where('cabang_id', auth()->user()->cabang_aktif_id)->get();
```

**Contoh yang SALAH** (akan bocor data cabang lain):
```php
$orders = Order::all(); // SECURITY BUG — lihat semua cabang!
```

**Aturan**: Setiap query yang menyentuh data cabang, cek dulu apakah sudah ada filter `cabang_id`. Ini security-critical.

### 🔴 3.5 Migration Harus Urut & Bisa Rollback
- Nama file migration harus datetime yang benar (bukan asal timestamp)
- Setiap `up()` HARUS ada `down()` yang balikin state
- Cek foreign key: tabel yang dirujuk harus dibuat DULU

### 🔴 3.6 Permission + Panduan + Tooltip + Tombol Cara Pakai Wajib Dibuat
Setiap fitur/menu BARU wajib:
1. Tambah entry di `PermissionSeeder` (untuk hak akses)
2. Tambah entry di `RolePermissionSeeder` (mapping role ke permission)
3. Tambah panduan di menu Panduan (via `PanduanKontenSeeder`)
4. Tambah tooltip untuk form-form penting (via `TooltipKontenSeeder`)
5. **Embed tombol "Cara Pakai" (`<x-panduan-button slug="{slug}" />`) di halaman fitur** — pojok kanan atas header halaman, atau header section untuk sub-fitur (mis. section Varian di form Produk Jual). Panduan yang cuma bisa diakses lewat menu Panduan (bukan langsung dari halaman fiturnya) dianggap **belum lengkap**.

Ini WAJIB, bukan optional. Kalau lupa, kerjaan bakal dobel di akhir.

**Validasi (2026-09-16)**: audit menyeluruh Tahap 1-6 menemukan Permission + Role Mapping SELALU konsisten diisi di setiap tahap sebelumnya (0 gap) — tapi Panduan + Tooltip 2 poin terakhir kelewat di 6 dari 9 fitur baru. Backfill konten sudah dilakukan (lihat 4.11), TAPI backfill pertama itu sendiri kelewat poin ke-5 (tombol Cara Pakai) — ketahuan dari test manual, bukan dari checklist. Backfill kedua (embed `<x-panduan-button>` ke 13 halaman) sudah menutup gap ini. **Pelajaran**: poin 1-2 (Permission/Role) rawan "otomatis kepikiran" karena langsung berhubungan dengan akses/keamanan (kalau lupa, fitur langsung error 403 — cepat ketahuan). Poin 3-5 (Panduan/Tooltip/Tombol) TIDAK menyebabkan error apapun kalau kelewat — silently missing, baru ketahuan kalau ada yang audit atau test manual. Ke depan: checklist section 13 WAJIB dicentang eksplisit per poin (termasuk poin 5), jangan cuma diingat — dan "panduan sudah ditulis" TIDAK SAMA dengan "panduan sudah accessible dari halaman fitur".

---

## 4. TEMUAN AUDIT PENTING (WAJIB DIINGAT)

### 4.1 🔴 BUG SILENT: Hardcoded `jasa_giling` + `berat_daging`

**Lokasi**:
- `app/Services/BepOtomatisService.php` → filter query pakai `tipe_order='jasa_giling'` + `berat_daging_kg`
- `app/Services/LoyaltyService.php` (method `auto_track`) → sama

**Efek untuk D'mentai**:
- Dashboard BEP tampil kosong/nol (karena tidak ada order tipe `jasa_giling`)
- Loyalty poin `auto_track` tidak pernah bertambah otomatis (silent fail, no error)

**Rencana Fix**:
- Extend service supaya bisa handle `tipe_order='penjualan'` juga
- Basis perhitungan diubah dari kg → jumlah pcs / total belanja Rp
- Dikerjakan saat menyentuh modul BEP & Loyalty (Tahap 5 & 6)

### 4.2 🟡 2 SISTEM LOGO TERPISAH — PERANGKAP BRANDING

**Lokasi**:
- **Mekanisme 1 (dinamis)**: `PengaturanUmum` model → upload via UI `/pengaturan/umum` → Storage
- **Mekanisme 2 (statis)**: file `public/images/logo.png` → dipakai favicon, PWA icon, PDF header

**Efek**: User upload logo baru di Pengaturan Umum → cuma muncul di TV Antrian, tidak di sidebar/PDF/favicon.

**Rencana Fix (Tahap 1 - Branding)**:
- Replace file statis `public/images/logo.png` dengan logo D'mentai
- Update `PengaturanUmum` default seed → "D'mentai"
- IDEAL: bikin fitur "logo master" di Pengaturan Umum yang override 2 mekanisme (opsional, kompleksitas tinggi)

### 4.3 🟡 PWA ICON HARDCODE (harus siapkan manual)

**Fakta**: `config/laravelpwa.php` 100% hardcode. 8 ukuran PNG (72-512px) harus disiapkan external, tidak ada UI admin atau command artisan untuk generate.

**Rencana**:
- Generate 8 ukuran PWA icon dari logo D'mentai full
- Tools: Imagemagick / ImageMagick PHP / online tool (pilih saat eksekusi)
- Dikerjakan di Tahap 1 - Branding

### 4.4 🟢 CabangScope DORMANT — JANGAN DIUBAH

**Fakta**: `app/Models/Scopes/CabangScope.php` tidak pernah aktif. Ke-62 pemanggilan `withoutGlobalScope(CabangScope::class)` adalah no-op.

**Aturan**: JANGAN diubah atau dihapus — terlalu banyak tempat, salah satu bisa bug. Biarkan sebagai "dokumentasi diri sendiri" bahwa dulu ada rencana global scope.

### 4.5 🟢 Tailwind + Alpine DEAD CODE

**Fakta**: `package.json` include Tailwind + Alpine, tapi 100% tidak dipakai di app real. Cuma tersisa di Breeze scaffolding (`welcome.blade.php`, register form) yang tidak dipakai.

**Rencana**: Skip dulu, cleanup di Tahap 7 (Testing/Go-live).

### 4.6 🟢 BOOTSTRAP APP MINIMALIS

**Fakta**: `bootstrap/app.php` cuma 2 middleware alias (`role`, `cabang`). Tidak ada middleware global tambahan. `routes/api.php` tidak terdaftar. Exception handling kosong (tidak ada custom 403/404/500).

**Aturan**: 
- Kalau nambah route API, harus register `api.php` dulu di `bootstrap/app.php`
- Kalau butuh middleware global, tambah di `bootstrap/app.php` (bukan `app/Http/Kernel.php` — file itu tidak ada di Laravel 12)

### 4.7 🟢 Recurring Transaction Trigger On-Request

**Fakta**: `AppServiceProvider::boot()` panggil `triggerRecurringIfNeeded()` dengan cache key harian. Jadi recurring dipicu on-request, bukan cuma cron.

**Aturan**: Kalau modif logic recurring, hati-hati — dia dipanggil setiap request.

### 4.8 🟢 `items.jenis` vs `items.tipe` — 2 Axis Orthogonal, JANGAN Digabung

**Fakta**: `items.jenis` (`bahan_baku`/`perlengkapan`, dari modul "Perlengkapan Habis Pakai" warisan Berkah Mulyo) dan `items.tipe` (5 nilai sejak Tahap 2.5, lihat 7.1) adalah **2 kolom independen** yang KEBETULAN sama-sama punya nilai literal `'bahan_baku'` — tapi maknanya beda total (satu soal "barang produksi vs ATK", satu lagi soal "dijual di POS atau tidak"). Jangan pernah asumsikan salah satu bisa dihitung dari yang lain.

### 4.9 🟢 `item_cabang` — Fallback "Tanpa Row = Aktif di Semua Cabang"

**Fakta**: Pivot `item_cabang` (Tahap 2.5) dipakai POS untuk filter ketersediaan produk_jual/produk_tambahan per outlet + `harga_override`. Item yang TIDAK PUNYA row sama sekali di tabel ini dianggap **aktif di semua cabang** (`Item::tersediaDiCabang()`) — sengaja begitu supaya 21 item dummy lama (belum pernah di-assign eksplisit lewat form Produk Jual baru) tidak hilang dari POS. Produk BARU yang dibuat lewat menu Produk Jual selalu dapat row eksplisit per cabang (checked/unchecked), jadi behaviour-nya deterministik ke depan — fallback ini murni utk data lama.

### 4.10 🟢 Permission Naming: `item.*` (Flat) vs `master.produk_jual.*`/`master.bahan_baku.*` (Namespaced)

**Fakta**: `item.*` (CRUD generik warisan Berkah Mulyo, tetap ada sebagai "Master Barang (Lengkap)" — unified fallback semua 5 tipe) dipakai bareng dengan 2 set permission baru `master.bahan_baku.*`/`master.produk_jual.*` (menu split Tahap 2.5) — pola namespace `master.*` ini konsisten dengan `master.item_varian.*`/`master.resep_bumbu.*` yang sudah ada duluan. Ketiga controller (`ItemController`, `MasterBahanBakuController`, `MasterProdukJualController`) sama-sama baca/tulis tabel `items` yang SAMA — bukan 3 sumber data terpisah, cuma beda filter+form+permission gate.

### 4.11 🟢 Backfill Panduan & Tooltip Fitur Tahap 1-6 (2026-09-16)

**Temuan**: Audit lengkap terhadap 9 fitur baru sejak fork (Tahap 1-6) menunjukkan Permission + Role Mapping SELALU lengkap (0 gap — kemungkinan karena lupa permission langsung menghasilkan error 403 yang cepat ketahuan saat testing), tapi **Panduan + Tooltip kelewat di 6 fitur**: Master Bahan Baku, Master Produk Jual, Setoran Kasir, Dashboard Owner (widget setoran), Laporan Setoran Kasir, dan Pengaturan Umum (branding) — plus 1 panduan **stale** (`item-varian` masih bilang "Coming Soon" padahal UI-nya sudah live sejak Tahap 3).

**Backfill yang dilakukan**: 6 panduan baru + 1 rewrite (semua via `Panduan::updateOrCreate()`, format `## Tujuan → ## Langkah-langkah → ## Catatan Penting → ## Troubleshooting`, 300-800 kata) + 20 tooltip baru (`master_produk_jual.*`, `master_bahan_baku.*`, `pos.*` tambahan, `setoran_kasir.*`, `laporan_setoran_kasir.*`, `dashboard_owner.*`) di `TooltipKontenSeeder.php`, DI-EMBED langsung ke `<x-tooltip key="...">` di 7 file view terkait (bukan cuma masuk tabel tanpa dipakai — cek ulang, tooltip yang tidak dipanggil di view TIDAK tampil ke user sama sekali).

**Bug pre-existing ditemukan & diperbaiki sekalian**: `resources/views/components/tooltip.blade.php` melakukan `e()` manual pada title+content lalu mencetak hasilnya lewat `{{ }}` (yang otomatis escape lagi) — DOUBLE-ESCAPE untuk tooltip mana pun yang kontennya mengandung karakter `&`/`<`/`>` (mis. tooltip baru `laporan_setoran_kasir.filter_status` yang berisi "Menunggu, Disetujui **&** Ditolak" tampil sebagai `&amp;amp;` bukan `&`). Fix: ganti jadi `{!! $tipContent !!}` (variabel sudah di-escape manual sebelumnya, tidak perlu escape kedua). Bug ini laten sejak tooltip component dibuat — mempengaruhi SEMUA tooltip lama yang kebetulan mengandung karakter HTML-special, bukan cuma yang baru ditambah sesi ini.

**Metode verifikasi**: 19 HTTP Feature test baru (`tests/Feature/Tahap7/PanduanTooltipBackfillTest.php`) — akses tiap slug panduan via route asli, cek isi (bukan cuma render OK), cek tooltip benar-benar muncul di response HTML form terkait (bukan cuma ada di tabel `tooltips`), regresi ke panduan/tooltip lama.

### 4.12 🟢 Tahap 7 — Final Polish & Go-Live (2026-09-14)

**A. Cleanup dead code**: Hapus `welcome.blade.php` + `layouts/navigation.blade.php` (dikonfirmasi 0 reference — root `/` adalah closure custom, bukan `view('welcome')`; navigation.blade.php tidak pernah di-`@include`). Hapus `tailwind.config.js` + `postcss.config.js`. `package.json` devDependencies dipangkas 11→5 (buang `tailwindcss`, `@tailwindcss/*`, `autoprefixer`, `postcss`, `alpinejs`). `resources/css/app.css` dan `resources/js/app.js` DIKOSONGKAN (bukan dihapus) — biar `vite.config.js` tetap valid, tidak ada view aktif yang `@vite()` isinya. **Register route & profil (`register.blade.php`, `/profile`) SENGAJA DIBIARKAN** — bukan dead code, itu keputusan produk (bisa dipakai suatu saat), bukan scope cleanup.

**B1. Fix `BepOtomatisService` hardcode `jasa_giling`+kg** (lihat [[4.1]]): pola fix = **fallback by data presence** — cek dulu apakah ada order `tipe_order='jasa_giling'` di periode query; kalau ADA pakai basis kg (logic lama, backward compat data historis), kalau TIDAK ADA (kasus normal D'mentai) pakai basis pcs dari `order_items.qty` dengan `tipe_order='penjualan'`. **Nama key return array (`volume_kg`, dst) SENGAJA DIPERTAHANKAN** meski semantiknya berubah (kg→pcs) — trade-off disetujui owner untuk hindari rename yang beresiko regresi di 6+ file konsumen; unit sebenarnya didokumentasikan via `@return array{...}` PHPDoc di `hitungBepOtomatis()`. `BusinessOverviewService::hitungProduksiSummary()` (dipakai laporan eksekutif PDF) punya bug identik, ikut difix dengan pola sama sebagai efek samping temuan audit.

**B2. Fix `LoyaltyService::auto_track` hardcode kg** (lihat [[4.1]]): migration widen enum `loyalty_programs.sumber_data` dari 1 nilai (`orders.berat_daging_kg`) jadi 3 (+`orders.total_bayar`, `orders.count`). Service pakai helper `resolveAgregat()` untuk branch SUM(total_bayar) / COUNT(*) / SUM(berat_daging_kg) sesuai `sumber_data` program. Basis kg lama tetap jalan apa adanya untuk program existing (backward compat, tidak perlu migrasi data). Form create/edit loyalty program ditambah dropdown "Basis Perhitungan"; `satuan_qty` di-derive otomatis dari `sumber_data` yang dipilih (Rp / transaksi / kg).

**B3. Sinkron 2 mekanisme logo** (lihat [[4.2]]): dibuat `PengaturanUmumObserver::saved()`, registrasi di `AppServiceProvider::boot()`. Saat `PengaturanUmum.logo_path` berubah → otomatis copy isi file ke `public/images/logo.png` (mekanisme statis). **Scope SENGAJA dibatasi cuma logo utama** — TIDAK menyentuh `logo-icon.png` atau 8 ukuran PWA icon (itu tetap manual sesuai [[4.3]], kompleksitas generate ulang PWA icon di luar scope Tahap 7).

**C. Error pages 403/404/500** di-restyle (bukan dibuat baru — file-nya sudah ada dari base Berkah Mulyo) pakai palet brand D'mentai (`#1A1A1A`/`#FF6B00`/`#FFF8E7`), logo D'mentai, tombol CTA gradient balik ke dashboard/login.

**Verifikasi**: 14 test baru `BepLoyaltyExtendTest.php` (B1+B2, termasuk 3 test backward-compat data lama) + 7 test `ErrorPagesAndLogoSyncTest.php` (error pages + sinkron logo real-filesystem + regresi cleanup) + 14 test `EndToEndFlowTest.php` (4 alur bisnis end-to-end: kasir jualan→setor, HO approve→dashboard update, permission 6 role, konsistensi tombol Cara Pakai). Total 118 test lintas Tahap 2.5/5/6/7 PASS, 0 regresi.

### 4.13 🔴 Bug Fix Ronde 2 — Temuan Test Manual Final Sebelum Go-Live (2026-09-15)

Setelah Tahap 7 "selesai" ([[4.12]]), test manual final Owner menemukan 4 bug baru (di luar 7 tahap resmi) — 3 di antaranya genuine bug produksi, bukan cuma polish:

- **Bug 1 (Dropdown Cabang Aktif cuma tampil sampai Outlet 2)**: root cause BUKAN CSS overflow seperti dugaan awal — `CabangMiddleware` memfilter `$userCabangs` (daftar cabang di dropdown switcher) ke `$user->cabangs()` (pivot `cabang_user` eksplisit) BAHKAN untuk role `canAccessAllBranches()` (owner/admin_pusat). Dikonfirmasi data dev: Owner/Admin Pusat cuma punya 3/6 pivot cabang. Fix: role dengan `canAccessAllBranches()` → tampilkan SEMUA cabang aktif, bukan cuma yang di-pivot. CSS `max-height`+`overflow-y:auto` tetap ditambahkan sebagai defensive fix kedua (kalau outlet bertambah banyak ke depan, sesuai filosofi fleksibilitas [[1.4]]).
- **Bug 2 (Bill Tersimpan POS)**: 3 perbaikan — (a) posisi dipindah ke PALING BAWAH halaman POS, di luar `<form id="formPos">` (menu sekunder, jangan ganggu alur transaksi baru); (b) tombol Batalkan BARU dengan permission `order.bill_tersimpan.batalkan` (default admin_pusat) — reuse `PenjualanService::batalkan()` yang sudah otomatis skip reversal stok/kas kalau order bukan status Selesai (bill Pending memang belum pernah potong stok apapun, jadi aman); (c) tombol "Bayar..." baru buka modal pembayaran lengkap (Tunai/Transfer/QRIS/Gojek/Grab + Split Payment + pilih Kas), reuse endpoint `charge-bill` yang sama dengan "Tunai Pas" existing.
- **Bug 3 🔴 KRITIS (Data Ghost di Produk Jual)**: root cause **nested `<form>` HTML**, BUKAN delete-recreate/filter-salah seperti 3 dugaan awal. `master/produk-jual/_form.blade.php` dan `master/bahan-baku/edit.blade.php` punya form "Zona Berbahaya" (hapus) yang ter-nested di dalam form utama (Simpan). Browser membuang tag `<form>` dalam yang bersarang tapi tetap memasukkan child input-nya (termasuk hidden `_method=DELETE`) ke form LUAR — karena posisinya di DOM SETELAH hidden `_method=PUT` bawaan form utama, klik "Simpan Produk" ter-method-spoof jadi DELETE (PHP: nilai `_method` TERAKHIR yang menang di `$_POST`), produk ke-soft-delete alih-alih ter-update. **Data TIDAK hilang permanen** (soft-delete, `CascadeDeleteService::deleteItemCascade()` pakai `$item->delete()` bukan `forceDelete()`) — bisa direstore via `/trash?model=items`. Kebingungan "Data Terhapus kelihatan kosong" murni UX (default tab "Orders", bukan bug kedua). Fix: pisahkan form hapus jadi SIBLING (bukan nested), tombol hapus pakai HTML5 `form="id"` attribute biar tetap tampil di card sidebar tanpa jadi child DOM form utama. Lihat [[4.14]] untuk guard rail permanennya.
- **Bug 4 (Audit Sync)**: audit menyeluruh pasca Bug 1-3 — smoke test 187 route GET, guard rail nested-form seluruh project, cek coverage Data Terhapus, cek 4 komponen wajib fitur baru. Menemukan 2 bug tambahan independen (Bug 5, Bug 6 di bawah) sebagai efek samping audit ini.

**Bonus temuan audit sync (disetujui Owner untuk sekalian difix, kriteria: crash/data-loss/silent-failure + fix <15menit + pola sudah ada di codebase)**:
- **Bug 5 (`notifikasi/index.blade.php`)**: nested form SAMA PERSIS pattern-nya dengan Bug 3 — form "Tandai Semua Dibaca" (POST) nested di dalam form filter (GET). Efeknya BUKAN data loss, tapi **silent failure**: tombol ter-asosiasi ke form GET yang salah, jadi klik "Tandai Semua Dibaca" cuma reload halaman filter, TIDAK benar-benar memanggil endpoint `notifikasi.read-all`. Fix: sibling form + `form="id"` attribute, sama seperti Bug 3.
- **Bug 6 (`RangkumanFinalService::hitungHealthScore()`)**: crash 500 di Laporan Eksekutif (`/laporan/eksekutif/preview` & `/export`) kalau cabang/periode TIDAK ADA penjualan sama sekali bulan berjalan (`margin_persen` null, `skalakan()` butuh `float` non-null). Ditemukan lewat smoke test 187 halaman, BUKAN bagian fitur Tahap manapun (laten dari Laporan Eksekutif versi lama). Severity dianggap kritis karena akan **natural terpicu di production** (outlet baru buka bulan ini = belum ada penjualan = kondisi ini persis). Fix: null-check eksplisit sebelum `skalakan()`, pola sama seperti `rasio_lancar`/rasio kewajiban-aset di fungsi yang sama.

**Metode verifikasi**: `Bug1CabangSwitcherScrollTest.php`, `Bug2BillTersimpanTest.php`, `Bug3NestedFormFixTest.php`, `Bug5NotifikasiNestedFormFixTest.php`, `Bug6RangkumanFinalNullMarginTest.php`, `DataTerhapusCoverageTest.php`, `NestedFormGuardRailTest.php`, `SmokeTestSemuaMenuTest.php` — semua di `tests/Feature/Tahap7/`.

### 4.14 🔴 Nested `<form>` HTML — POLA HARAM, DIGUARD RAIL

**Aturan mutlak**: `<form>` TIDAK BOLEH nested (bersarang) di dalam `<form>` lain, di file blade manapun, ke depan tanpa kecuali.

**Kenapa ini bukan cuma "kode jelek" tapi BUG NYATA**: HTML5 parser membuang tag `<form>` yang bersarang (invalid), TAPI child input-nya (hidden `_token`/`_method`, dst) tetap masuk ke DOM sebagai bagian dari form LUAR. Efeknya tergantung isi form dalam:
- Kalau form dalam punya `@method('DELETE')`/`PUT` yang beda dari form luar → **method-spoofing salah sasaran** (submit form luar memicu action form DALAM secara tidak sengaja) — ini yang terjadi di [[4.13]] Bug 3 (data ghost, KRITIS)
- Kalau form dalam action-nya beda tapi method sama → tombol form dalam **ter-asosiasi ke form luar yang salah**, silent tidak memanggil endpoint yang dimaksud — ini yang terjadi di Bug 5 (notifikasi tandai dibaca)

**Fix standar**: pisahkan jadi 2 form SIBLING (bukan nested). Tombol yang secara visual perlu ada "di dalam" card/section form lain pakai **HTML5 `form="id-form-lain"` attribute** pada `<button type="submit">`-nya — submit ke form yang benar tanpa perlu nested DOM. Lihat `master/produk-jual/_form.blade.php`, `master/bahan-baku/edit.blade.php`, `notifikasi/index.blade.php` untuk contoh pola fix-nya.

**Guard rail permanen**: `tests/Feature/Tahap7/NestedFormGuardRailTest.php` — scan SEMUA file `.blade.php` project tiap kali test suite dijalankan, gagal kalau ada nested form baru manapun. **WAJIB tetap PASS** — kalau gagal karena fitur baru, JANGAN suppress test-nya, perbaiki struktur form-nya (sibling + `form="id"` attribute).

### 4.15 🟢 Rename UI "Jenis Olahan" → "Jenis Menu", "Resep Bumbu Standar" → "Master Bumbu Pusat" (2026-09-15)

**Latar belakang**: audit read-only menemukan 2 menu warisan Berkah Mulyo ini masih hidup dan dipakai (bukan dead code sepenuhnya), tapi seluruh isi panduan+UI-nya mendeskripsikan alur **jasa-giling lama yang sudah tidak ada** ("dropdown 🧂 Pilih Resep Bumbu di POS", "Terapkan Resep", "Berat Gilingan (kg)") — endpoint AJAX pendukungnya (`GET /pos/resep-bumbu/{id}`) dikonfirmasi **0 caller** di `resources/views` manapun. Keputusan: **rename + perbaiki panduan** (bukan hapus) — route name/permission name/model/table SEMUA TETAP (`jenis-olahan.*`, `resep-bumbu`, `master.resep_bumbu.*`, tabel `jenis_olahans`/`resep_bumbu` tidak disentuh), zero migration.

- **"Jenis Olahan" → "Jenis Menu"**: label di sidebar, judul halaman, judul kolom Laporan Laba Rugi ("Per Jenis Olahan" → "Per Jenis Menu", key query `jenis_olahan` TETAP), tooltip, dan panduan (`slug: jenis-olahan`) — direposisi sebagai kategorisasi referensi untuk Master Bumbu Pusat, BUKAN lagi "dropdown POS transaksi Jasa Giling".
- **"Resep Bumbu Standar" → "Master Bumbu Pusat"**: label di sidebar, judul halaman, `display_name` permission, dan panduan (`slug: resep-bumbu`) ditulis ulang total — sekarang eksplisit menjelaskan bahwa **cara utama kelola resep adalah lewat form Produk Jual** (section Komposisi/Resep, lihat Tahap 2.5), halaman ini murni overview/referensi. Kolom BARU **"Produk Terhubung"** ditambahkan ke tabel index (`ResepBumbuController::index()` eager-load relasi `item`) supaya user langsung lihat resep mana yang genuinely dipakai POS vs orphan.
- **⚠️ Peringatan ditambahkan eksplisit di panduan**: tombol "Tambah Resep" di halaman Master Bumbu Pusat membuat resep TANPA `item_id` (orphan, tidak pernah dipakai POS karena POS cuma menemukan resep lewat produk yang di-klik kasir) — user diarahkan pakai form Produk Jual untuk resep baru. Tombolnya SENGAJA TIDAK dihapus/diubah perilakunya di sesi ini (di luar scope "rename UI + panduan"; kalau mau ditutup beneran, lihat rekomendasi audit terpisah).
- **Info box BARU** ditambahkan ke modal "Tambah Kategori Baru" (satu-satunya UI kelola `item_categories`, ada di `item/create.blade.php` & `item/edit.blade.php` — TIDAK ada halaman "Master Kategori Item" terpisah) menjelaskan kategori baru tidak langsung muncul sebagai chip filter POS (baru muncul kalau sudah punya ≥1 produk aktif).

**TODO awalnya dicatat di sini (2026-09-15) sudah DIKERJAKAN 2026-09-17** — lihat [[4.16]] fitur "Import dari Bumbu Pusat".

**Verifikasi**: `tests/Feature/Tahap7/RenameJenisMenuMasterBumbuPusatTest.php` (10 test) — label baru tampil di halaman+panduan, teks lama sudah hilang, route/permission name regresi tidak berubah, tombol Cara Pakai tetap ada, kolom Produk Terhubung menampilkan nama item.

### 4.16 🟢 Fitur "Import dari Bumbu Pusat" — Link (Bukan Copy) Resep Antar Produk (2026-09-17)

**Konsep**: Master Bumbu Pusat (`ResepBumbu` tanpa `item_id`, sebelumnya orphan tidak berguna — lihat [[4.15]]) sekarang bisa di-**link** ke resep produk manapun lewat tombol **"Import dari Bumbu Pusat"** di form Produk Jual. 1 baris resep produk sekarang `item_id` langsung (bahan manual) ATAU `resep_bumbu_ref_id` (link ke Master Bumbu Pusat) — mutually exclusive, divalidasi di `ProdukJualRequest::withValidator()`.

**Skema**: migration `2026_09_17_600002` — `resep_bumbu_items.item_id` dibuat nullable, tambah kolom `resep_bumbu_ref_id` (FK nullable ke `resep_bumbu.id`, `nullOnDelete`).

**Anti cyclic-reference BY CONSTRUCTION (bukan cuma validasi runtime)**: `MasterResepBumbuController::storeItem()` (form Master Bumbu Pusat sendiri) **TIDAK PERNAH** menerima field `resep_bumbu_ref_id` — item milik sebuah bumbu SELALU `item_id` langsung. Kombinasi dengan aturan "cuma bisa link ke `ResepBumbu` yang `item_id IS NULL`" (`MasterProdukJualController::syncResep()`) membuat kedalaman referensi **maksimal 1 level** (Produk → Bumbu → Bahan Mentah) secara struktural — cycle tidak mungkin terjadi apapun inputnya, tidak perlu deteksi cycle runtime/rekursi.

**🟢 Pattern "Live Calculation, No Cache/Job" untuk auto-update lintas-entitas**: HPP dan potong-stok baris linked dihitung **live** setiap kali dibutuhkan (`ResepBumbuItem::expandKeBahanMentah()` — expand 1 baris jadi kebutuhan bahan mentah on-the-fly, dipanggil dari `PenjualanService::cekResepCukup()`/`potongStokUntukItem()` saat checkout DAN `MasterProdukJualController::kalkulatorResep()` saat preview). **TIDAK ADA snapshot/cache/job apapun** — begitu Master Bumbu Pusat diedit, SEMUA produk yang link ke situ otomatis dapat angka baru di request berikutnya, tanpa event listener/queue/invalidasi cache. Prinsip ini konsisten dengan pola lama "Jangan input harga di Master Resep" (harga selalu live dari `Item::harga_beli_terakhir`) — cukup diperluas 1 level referensi. **Kapan pola ini BUKAN pilihan tepat**: kalau perhitungan yang di-live-kan mahal (query berat/N+1 dalam jumlah besar) atau butuh nilai historis-beku (mis. harga di struk transaksi lama TIDAK BOLEH ikut berubah kalau master harga diedit — itu sebabnya `order_items.hpp`/`harga_satuan` tetap snapshot permanen, beda dari resep yang genuinely representasi "kondisi sekarang").

**Permission**: reuse `master.produk_jual.edit` (tidak ada permission baru) — keputusan Owner: 1 tombol = 1 permission, siapa yang boleh edit produk otomatis boleh import bumbu.

**File utama**: migration `2026_09_17_600002_add_resep_bumbu_ref_to_resep_bumbu_items_table.php`, `ResepBumbuItem::expandKeBahanMentah()`/`isLinked()`, `MasterProdukJualController::{syncResep,kalkulatorResep,listBumbuPusat}()`, `ProdukJualRequest::withValidator()`, `PenjualanService::{cekResepCukup,potongStokUntukItem}()` (expand-aware), `master/produk-jual/_form.blade.php` (modal picker + badge baris linked).

**Verifikasi**: `tests/Feature/Tahap7/ImportBumbuPusatTest.php` (21 test) — struktur data, modal picker (render/search/permission), import=link bukan copy, HPP akurat (manual+linked mix), auto-update HPP tanpa sentuh produk, hapus link vs master tetap ada, edge case (bumbu nonaktif tetap jalan utk link lama, cyclic reference structural block 2 arah), regresi fitur existing (create/edit/varian/foto/kalkulator/Master Bumbu Pusat CRUD/POS checkout stok terpotong benar).

### 4.17 🟢 Improvement Test Manual Production: Rename Label "Nama" + Hapus Gojek/Grab (2026-09-18)

**Latar belakang**: 2 temuan test manual Owner di production (tablet POS) — (a) label "Nama (jika tidak terdaftar)" kepanjangan, wrap 2 baris di layar sempit; (b) 5 tipe pembayaran (Tunai/Transfer/QRIS/Gojek/Grab) dianggap terlalu ramai untuk bisnis retail walk-in D'mentai (bukan food delivery). Sebelum eksekusi, audit read-only menyeluruh dijalankan dulu (grep semua file yang sentuh "gojek"/"grab" + cek data historis production via Owner) — hasilnya **0 baris data** pakai Gojek/Grab baik di dev maupun production, sehingga aman dieksekusi tanpa risiko data loss.

- **Rename label**: `resources/views/penjualan/pos.blade.php` — "Nama (jika tidak terdaftar)" → "Nama" (placeholder "Nama pelanggan / walk-in" DIPERTAHANKAN, masih relevan sebagai hint).
- **Hapus Gojek/Grab dari `TipePembayaran` enum**: `Gojek`/`Grab` cases dihapus, `kasKategori()` disederhanakan (dulu ada branch khusus Gojek/Grab→'transfer', sekarang tinggal `return $this->value` krn cuma 3 case tersisa yang semuanya kasKategori = value-nya sendiri).
- **Migration** `2026_09_18_700001_hapus_gojek_grab_dari_enum_tipe_pembayaran.php` — pola **"data-migrate unconditional lalu alter enum"** dalam 1 migration: `UPDATE ... WHERE tipe_pembayaran/metode IN ('gojek','grab') SET = 'transfer'` (no-op aman kalau 0 baris) diikuti `ALTER TABLE ... MODIFY ENUM('tunai','transfer','qris')` pada `orders.tipe_pembayaran` dan `order_payments.metode`. Diverifikasi reversibel (`migrate:rollback` → enum balik ke 5 opsi → `migrate` lagi → balik ke 3 opsi, tanpa data loss krn dev/production sama-sama 0 baris affected).
- **Blast radius kode**: validasi (`PenjualanController`, `OrderRequest`), breakdown hardcode (`SetoranKasirService` — array loop 5→3 metode), UI POS (~10 titik: tombol utama, 2 dropdown split-payment, modal Bill Tersimpan, 3 baris JS), UI Setoran Kasir (2 baris tabel breakdown), Panduan (6 lokasi di slug `pos` + `setoran-kasir`). **TIDAK perlu diubah**: `PenjualanController::edit()`+`SetoranHarianService` (sudah pakai `TipePembayaran::cases()` dinamis, otomatis adaptif), model `Order`/`OrderPayment` (cast enum, ikut definisi), Dashboard/Laporan Setoran Kasir (iterate `setoran_details` dinamis), Tooltip (0 hasil grep — tidak ada yang menyebut Gojek/Grab), seeder `KategoriTransaksi` (0 hasil — tidak ada mapping kategori ke situ).
- Migration lama (`2026_09_13_200002`, `2026_09_13_200006`) yang ORIGINALLY menambahkan Gojek/Grab **SENGAJA TIDAK diedit** — migration yang sudah pernah jalan di production tidak boleh diubah isinya, riwayatnya tetap sebagai catatan historis; migration baru inilah yang jadi "penyeimbang"-nya.

**Verifikasi**: `tests/Feature/Tahap7/HapusGojekGrabTest.php` (12 test) — label baru, tombol Gojek/Grab hilang dari POS, enum cuma 3 value (app+DB), submit order 3 metode valid, submit dgn `gojek`/`grab` ditolak validasi (baik lewat `POST /penjualan` maupun `POST .../charge`), breakdown Setoran Kasir 3 metode, submit setoran total akurat, panduan sudah bersih. 1 test lama (`Tahap5\SetoranKasirHttpTest`) diupdate assertion-nya (`assertCount(5,...)` → `assertCount(3,...)`) krn memang sengaja berubah oleh perubahan ini, bukan regresi tak terduga.

### 4.18 🔴 Foto Produk Tidak Tampil di Production (Rumah Web Shared Hosting) — Root Cause Ganda + Fix Override `asset()`

**Konteks**: Production (`erpdimsum.azwacore.com`, Rumah Web shared hosting cPanel) TIDAK PUNYA terminal/SSH, dan `symlink()` PHP DIBLOKIR provider — `php artisan storage:link` (jalan normal di XAMPP lokal, lihat [[10.5b]]) tidak bisa dipakai sama sekali di production. Foto produk (upload sukses, file genuinely ada di `storage/app/public/produk/`) tidak tampil di UI manapun (Master Produk Jual, POS). 4 percobaan fix awal (proxy `.htaccess`, ubah config `filesystems.php`, tambah route `/storage/{path}`, replace compiled views) SEMUANYA gagal — audit menemukan **2 root cause independen** yang saling menjelaskan kenapa keempatnya gagal:

**Root Cause A — `.htaccess` bawaan blokir `/storage/*` SEBELUM sampai Laravel**: `public/.htaccess` (warisan template Berkah Mulyo, ada sejak initial commit) punya `RewriteRule ^storage/ - [L,NC]` — didesain utk kondisi `public/storage` adalah symlink/junction BENERAN (fast-path: Apache serve langsung tanpa lewat Laravel). Di production, symlink itu TIDAK ADA (diblokir), jadi Apache coba serve file yang tidak ada → 404 Apache → kemungkinan besar Rumah Web punya `ErrorDocument 404` custom yang fallback ke `index.php` → Laravel jalan tapi HANYA menemukan "tidak ada route match", render 404 branded-nya sendiri. **Rule `[L]` ini terminate proses SEBELUM custom `.htaccess` rule ATAU route Laravel `/storage/{path}` manapun sempat dievaluasi** — itu sebabnya Coba 1 (proxy rule) dan Coba 3 (route baru) sama-sama gagal walau masing-masing secara terpisah sudah benar.

**Root Cause B — URL foto di-hardcode `asset('storage/'.$item->foto)`, TIDAK baca config apapun**: tidak ada accessor `getFotoUrlAttribute()` di `Item.php`. 7 titik hardcode tersebar (`master/produk-jual/index.blade.php`, `_form.blade.php`, `penjualan/pos.blade.php`, `face-registration/index.blade.php`, `setoran-kasir/show.blade.php`, `FaceAttendanceController.php`, `FaceRegistrationController.php`). `asset()` Laravel HANYA menempelkan `APP_URL` — TIDAK PERNAH membaca `config('filesystems.disks.public.url')` (config itu cuma dipakai `Storage::url()`, yang justru dipakai 2 view LAIN — `karyawan/edit.blade.php`, `karyawan/show.blade.php` — inkonsistensi pola lama, bukan bug baru). Ini sebabnya Coba 2 (ubah config `url` ke `/asset`) 0 efek, dan Coba 4 (replace compiled views) 0 file diubah (URL bukan string statis di compiled view, tapi hasil `asset()` yang resolve saat runtime).

**Temuan tambahan penting**: codebase SUDAH PUNYA pola serupa yang battle-tested — route `Route::get('/img/{path}', ...)` (nama route `img.serve`, di `routes/web.php`) sudah lama dipakai utk foto absensi/face-attendance/bukti-transaksi/logo (>10 titik pemakaian, lihat `url('/img/'.$path)`). Route ini TIDAK kena blokir Root Cause A krn prefix-nya bukan `/storage/`. Ini validasi kuat bahwa strategi "serve dari `storage/app/public/` lewat route Laravel dgn prefix BUKAN `/storage/`" memang sudah proven jalan di environment production yang sama.

**Fix (Approach B, dipilih Owner)**: override `asset()` secara GLOBAL, bukan edit 7 view satu-satu:
- `app/Support/StorageAwareUrlGenerator.php` — subclass `Illuminate\Routing\UrlGenerator`, override `asset()`: path yang literal diawali `storage/` di-rewrite jadi `asset/` sebelum diteruskan ke `parent::asset()`. Path lain (css/js/images, URL absolute, atau `storage` yang cuma kebetulan ada di tengah string) TIDAK disentuh.
- `AppServiceProvider::register()` — daftarkan subclass ini via `$this->app->extend('url', ...)`, **MEREPLIKASI PERSIS** setup resolver yang dilakukan `Illuminate\Routing\RoutingServiceProvider` bawaan (session resolver, key resolver utk signed URL, `rebinding('request', ...)`, `rebinding('routes', ...)`) — kalau tidak direplikasi, fitur signed URL (mis. verifikasi email) akan diam-diam rusak. Diverifikasi eksplisit via test: `URL::signedRoute()` tetap generate signature valid (`hasValidSignature()` true).
- `app/Http/Controllers/StorageAssetController.php` + route baru `Route::get('/asset/{path}', ...)->name('storage.asset')` — stream file dari `Storage::disk('public')` (bukan filesystem langsung), ada guard path-traversal (`str_contains($path,'..')` → 404).
- **TIDAK edit `.htaccess` production** (lebih berisiko, sulit diverifikasi tanpa akses server) — pendekatan ini sepenuhnya di level aplikasi Laravel, deploy via git push seperti biasa.

**Kenapa override di level `UrlGenerator` (bukan edit 7 view)**: 1 file berubah scope-nya (`AppServiceProvider`), otomatis berlaku ke SEMUA pemanggilan `asset('storage/...)` — termasuk view baru di masa depan yang belum ditulis — tanpa perlu diingat "jangan lupa pakai helper khusus". Reversibel: kalau pindah hosting yang symlink-nya jalan normal, cukup hapus registrasi di `register()`.

**Verifikasi**: `tests/Feature/Tahap7/StorageAssetOverrideTest.php` (11 test) — path rewrite akurat (`storage/` di awal saja, bukan di tengah string), path lain (css/js/images) tidak terpengaruh, URL absolute passthrough, `route()`/`URL::signedRoute()` tetap valid (bukti resolver ter-preserve), route `/asset/{path}` genuinely serve file + 404 utk file tidak ada + tolak path traversal, end-to-end Master Produk Jual & POS render `/asset/...` bukan `/storage/...`. Full regression 205 test lintas fase PASS (0 regresi) — termasuk `SmokeTestSemuaMenuTest` (187 route) yang membuktikan override ini tidak menyebabkan 500 di halaman manapun.

**Deploy production**: script sekali-pakai `public/clear-cache.php` (WAJIB dihapus dari server setelah dijalankan — tidak ada proteksi auth) untuk `config:clear`+`view:clear`+`cache:clear`+`route:clear`+rebuild `config:cache`+`route:cache` via akses browser, karena production tidak punya terminal/SSH.

### 4.19 🟡 UI Preview "Harga Master" & "Subtotal" di Section Resep Produk Jual (2026-09-19)

**Laporan Owner dari production**: bahan "Isian Ayam" 45rb/kg, takaran 35 gram di resep, Total HPP tampil Rp 1.575.000 — kelihatan seperti bug hitung.

**Audit menemukan root cause SEBENARNYA lebih nuanced dari sekadar "bug hitung"**: `MasterProdukJualController::kalkulatorResep()` (dipakai tombol "Simulasi Produksi" di form admin) mengalikan `$ri->qty_per_unit` MENTAH (35, tanpa konversi satuan) dengan `harga_beli_terakhir` (Rp/kg) → 35×45000=Rp1.575.000. **Konversi satuan yang benar SUDAH ADA** di `ResepBumbuItem::getQtyPerUnitDalamKgAttribute()` (gram/ml÷1000, ons÷10) dan **SUDAH DIPAKAI DENGAN BENAR** di `expandKeBahanMentah()` — yaitu jalur yang genuinely dipakai POS checkout ([[4.16]]). **Kesimpulan krusial: HPP transaksi RIIL di POS TIDAK KENA bug ini** — cuma preview "Simulasi Produksi" di form admin yang salah karena skip accessor konversi yang sudah ada.

**Keputusan Owner (Opsi C — Kombinasi, bukan fix penuh)**:
- **Dikerjakan sekarang**: HANYA UI improvement, TIDAK fix logic konversi satuan `kalkulatorResep()`. Ditambahkan kolom **"Harga Master"** (readonly, live preview dari `harga_beli_terakhir`, format "Rp X / satuan") dan **"Subtotal"** (readonly, `qty × harga`) per baris resep manual — **KEDUANYA MURNI PERKALIAN APA ADANYA, TANPA KONVERSI SATUAN, SENGAJA** (bukan bug, keputusan produk eksplisit: "surface bad data, don't auto-correct" — supaya anomali input seperti "Rp45.000/gram" langsung KETAHUAN user secara visual sebelum Simpan, bukan disembunyikan lewat koreksi otomatis yang justru menutupi kesalahan input data aslinya di Master Bahan Baku). Baris resep ter-link ke Master Bumbu Pusat (badge 🧂) tampilkan placeholder "— (lihat Simulasi Produksi)" di kedua kolom (tidak applicable per-item, karena expand-nya multi-bahan). Info alert `alert-warning` di bawah tabel, link ke Master Bahan Baku. Kolom mode-harga existing di-rename "Harga" → **"Mode Harga"** biar tidak rancu dengan "Harga Master" baru.
- **Dideferred ke Sprint 2**: fix logic konversi satuan `kalkulatorResep()` itu sendiri (Opsi A, unit-family system) — lihat [[12.12]].
- **Data anomali "Isian Ayam" di production TIDAK difix oleh kode** — tanggung jawab Owner via UI Master Bahan Baku (cek satuan & harga item itu).

**Bug kedua ditemukan sebagai efek samping audit (BELUM difix, di luar scope eksplisit sesi ini)**: `ResepBumbuItem::getTotalHargaMasterAttribute()` (dipakai kolom "Total /kg" di `master/resep-bumbu/edit.blade.php`) pakai `item->harga_jual` (kosong untuk `bahan_baku`, field itu punyanya `produk_jual`) — harusnya `harga_beli_terakhir`, sehingga kolom itu selalu tampil Rp0 untuk bahan baku manapun.

**Implementasi**: HANYA 1 file view diubah (`resources/views/master/produk-jual/_form.blade.php`) — kalkulasi JS client-side murni, TIDAK ada endpoint AJAX baru (`BAHAN_OPTIONS` array yang sudah ada di JS ditambah field `harga`). **Bug tak terduga saat implementasi**: ekspresi kompleks `@json($bahanOptions->map(fn($b) => [...(float) ($x ?? 0)...]))` langsung di dalam directive `@json(...)` membuat Blade compiler SALAH PARSE argumen (comma-splitter Blade ter-confuse oleh kombinasi cast+null-coalesce+nested-paren di dalam closure), hasil compile PHP terpotong di tengah array literal → `ViewException: Unclosed '['`. **Pelajaran**: JANGAN taruh ekspresi PHP kompleks (cast, null-coalesce, nested function call majemuk) langsung sebagai argumen `@json()`/directive Blade lain — compute dulu di `@php` block jadi variabel sederhana, baru `@json($variabel)`.

**Verifikasi**: `tests/Feature/Tahap7/ResepHargaMasterPreviewTest.php` (12 test) — rendering kolom+alert, data `BAHAN_OPTIONS` (field `harga` benar termasuk kasus 0), placeholder baris linked, regresi (Import Bumbu Pusat, nested-form check, save resep manual/varian/edit resep existing, POS render). Full regression 217 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🔴 Addendum — Bug Fix Ronde 3 (2026-09-19), ditemukan Owner setelah Opsi B deploy**:

**Bug A — Simulasi Produksi baca angka BEDA dari form input**: Owner ubah qty "Isian Ayam" di form dari 35g jadi 0.2g TAPI belum klik Simpan, lalu klik tombol Simulasi Produksi → hasilnya tetap pakai 35g (angka lama). Root cause: `hitungKalkulator()` (JS) melakukan GET-fetch ke `MasterProdukJualController::kalkulatorResep()`, yang query resep **langsung dari DB** (`$produkJual->load('resep.items...')`) — bukan dari state form di browser. Kolom "Subtotal" (Opsi B, client-side) baca `qtyInput.value` langsung dari DOM sehingga benar (0.2g), sedangkan Simulasi Produksi baca snapshot DB lama — **2 sumber data berbeda, bukan salah hitung**. Fix: `hitungKalkulator()` sekarang serialize semua baris `#bodyResep` (fungsi `serializeResepUntukKalkulator()`) dan kirim via **POST** (route `kalkulator-resep` diubah jadi `Route::match(['get','post'], ...)` — GET tetap didukung utk backward compat) ke `kalkulatorResep()`, yang sekarang terima `request->input('resep')` (dinormalisasi via `normalisasiResepDariForm()` — batch-load `Item`/`ResepBumbu` by id, hindari N+1) dan **fallback ke DB kalau payload kosong**. Server tetap yang hitung (perlu expand Bumbu Pusat server-side untuk baris linked, tidak bisa pure-JS) — cuma sumber datanya sekarang form real-time, bukan DB snapshot.

**Bug B — Kolom Subtotal tidak ada Total footer**: ditambah `<tfoot>` di tabel resep dengan baris "Total HPP (Preview Cepat)" — JS `hitungTotalHpp()` sum semua `.subtotalPreview` yang berupa angka, dipanggil tiap `hitungPreviewBaris()` jalan + saat baris ditambah/dihapus (`hapusBarisResep()` baru, ganti inline `this.closest('tr').remove()`). **Baris linked (Bumbu Pusat) SENGAJA di-skip dari sum** (subtotal-nya butuh expand server-side, sama seperti Bug A) — ditandai via class `linkedBumbuMarker` di placeholder cell, memicu warning terpisah (`#warningBarisLinked`, hidden by default, muncul dinamis kalau ada ≥1 baris linked) yang mengarahkan user ke Simulasi Produksi Lengkap untuk total termasuk Bumbu Pusat.

**Konsolidasi 2 area total (Opsi C, disetujui Owner)** — masing-masing punya use case beda, TIDAK dihapus salah satu, dikasih label+caption biar tidak bingung:
- **"Total HPP (Preview Cepat)"** (footer tabel, caption: "instant, client-side, tanpa konversi satuan otomatis") — quick check per-baris manual, TANPA Bumbu Pusat.
- **"Simulasi Produksi Lengkap"** (tombol existing, caption: "Server-side, hitung dari isi form saat ini (belum perlu Simpan dulu), expand Bumbu Pusat penuh") — hitungan lengkap termasuk expand Bumbu Pusat, sekarang FIXED baca form real-time (Bug A).

**File yang diedit**: `resources/views/master/produk-jual/_form.blade.php` (footer + JS), `app/Http/Controllers/MasterProdukJualController.php` (`kalkulatorResep()` + `normalisasiResepDariForm()`), `routes/web.php` (`kalkulator-resep` GET→GET+POST).

**Verifikasi**: `tests/Feature/Tahap7/SimulasiProduksiFormRealtimeTest.php` (8 test) — payload form override DB (0.2g bukan 35g → Rp9.000 bukan Rp1.575.000), fallback ke DB kalau payload kosong, expand Bumbu Pusat tetap benar dari payload form, skip baris invalid/kosong, tidak terganggu oleh flag `punya_varian`, label+caption baru tampil, markup footer+warning ada, regresi save produk masih normal. Full regression 225 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟡 Addendum kedua — Simplifikasi Ronde 4 (2026-09-19), 2 fix simpel diminta Owner**:

1. **Format qty rancu ("1.000" terbaca "seribu")**: kolom DB `qty_per_unit` DECIMAL(10,3) + Eloquent cast `'decimal:3'` balikin STRING fixed-3-desimal ("1.000", "0.200") yang ditaruh mentah ke `value=""` input — buat Indonesia (titik = pemisah ribuan) angka "1.000" terbaca seperti "seribu" padahal cuma 1 pcs. Fix: fungsi JS baru `formatQtyInput()` (`parseFloat` lalu `String()`, buang trailing zero) dipakai di kedua tempat qty di-render ke `value=` (baris manual & baris linked Bumbu Pusat) — berlaku SAMA rata semua satuan (owner minta simpel, tidak usah beda-beda per family satuan).
2. **Konsolidasi 2 mode Total HPP jadi 1**: tombol "Simulasi Produksi Lengkap" + `jumlahProduksi`/`hasilKalkulator` **DIHAPUS total** dari view (bukan disembunyikan). Footer "Total HPP" sekarang jadi SATU-SATUNYA total, dan sekarang BENAR untuk baris linked juga — baris Bumbu Pusat (🧂) hitung subtotal-nya sendiri via AJAX otomatis (`hitungSubtotalLinkedBaris()`, debounce 400ms, POST ke `kalkulatorResep()` yang sama, `jumlah=1` krn qty di baris linked sudah representasi "per 1 unit produk") begitu baris ditambah / qty diubah — hasilnya masuk ke cell `.subtotalPreview` yang sama dengan baris manual, jadi otomatis ke-total oleh `hitungTotalHpp()` tanpa logic skip/warning terpisah lagi (elemen `#warningBarisLinked` & class `linkedBumbuMarker` dihapus, sudah tidak relevan). Backend `kalkulatorResep()`/`normalisasiResepDariForm()`/route tidak berubah (sudah cukup fleksibel dari fix Ronde 3).

**File yang diedit**: HANYA `resources/views/master/produk-jual/_form.blade.php` — 0 perubahan controller/route/migration.

**Verifikasi**: 2 test lama diupdate assertion-nya (placeholder statis → "Menghitung..."+AJAX; label+warning lama → footer tunggal + tombol lama sudah hilang) + 2 test baru (`formatQtyInput` dipakai di kedua tempat, qty desimal tersimpan tetap render normal). Full regression 227 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🔴 Addendum ketiga — Bug Fix Ronde 5 (2026-09-19), 2 bug ditemukan Owner setelah Ronde 4 deploy**:

1. **Subtotal Bumbu Pusat selalu "—"/Rp0**: root cause — `hitungSubtotalLinkedBaris()` (JS) pakai `KALKULATOR_URL`, yang route-nya `/{produkJual}/kalkulator-resep` **butuh `Item` yang sudah tersimpan**. Di halaman **Create** belum ada `$item` sama sekali, jadi const `KALKULATOR_URL` bahkan tidak didefinisikan (dibungkus `@if($isEdit)`) — fungsi langsung short-circuit ke "—" tanpa pernah coba AJAX. Padahal subtotal 1 Bumbu Pusat TIDAK butuh produk sama sekali (cuma butuh: bumbu apa + qty berapa porsi). Fix: endpoint BARU `POST /master/produk-jual/preview-bumbu/{bumbu}` (`previewSubtotalBumbu()`) di-bind langsung ke `ResepBumbu` (bukan `Item`) — jalan di Create MAUPUN Edit tanpa syarat produk tersimpan. Logic expand-nya di-extract jadi `private hitungSubtotalBumbuTunggal(ResepBumbu $bumbu, float $qtyPerUnit, int $jumlah): float`, dipakai ULANG di `kalkulatorResep()` (branch linked, existing) supaya tidak duplikat logic. JS const baru `PREVIEW_BUMBU_URL_BASE` (template URL dgn placeholder `__ID__`, SELALU ada tidak bersyarat `$isEdit`) — `KALKULATOR_URL` dibiarkan apa adanya utk proses lain di halaman Edit.
2. **Total HPP salah jumlah** (contoh Owner: 1.200+2.250+200+800 → tampil 1.003, seharusnya 4.450): root cause — `hitungTotalHpp()` parse teks `"Rp 1.200"` pakai regex `[^0-9.-]` yang MENYISAKAN titik "jaga-jaga kalau ada desimal", padahal `formatRupiahPreview()` SELALU `Math.round()` sebelum format (tidak pernah ada desimal asli) — titik yang muncul SELALU pemisah ribuan Indonesia. `parseFloat("1.200")` dibaca JS sebagai `1.2` (titik = decimal separator di JS), bukan 1200 — persis match bukti Owner (`1.2+2.25+200+800=1003.45`→round→`1.003`). Fix: buang SEMUA karakter non-digit sebelum parse (`replace(/[^0-9]/g, '')`), bukan cuma sebagian.

**Bug tak terduga saat implementasi (ditemukan sendiri sebelum sempat mengganggu Owner)**: comment JS baru sempat menulis literal teks `@if($isEdit)` di dalam `//` comment sebagai penjelasan — Blade compiler MEMPARSE `@if(...)` di MANA SAJA di file (termasuk di dalam text yang secara visual "cuma komentar JS"), bukan JS-aware, sehingga `@if`/`@endif` count jadi tidak seimbang → `ViewException: unexpected end of file, expecting endif`. Fix: reword comment supaya tidak mengandung pola literal `@directive(...)`. **Pelajaran baru**: hindari menulis pola `@kata(...)` apapun (bahkan sekadar contoh/referensi) di dalam comment blade manapun — Blade tidak tahu bedanya "kode nyata" vs "teks yang kebetulan mirip directive".

**File yang diedit**: `app/Http/Controllers/MasterProdukJualController.php` (extract method + endpoint baru), `routes/web.php` (route baru, static path di atas `{produkJual}` sesuai pola `bumbu-pusat/list`), `resources/views/master/produk-jual/_form.blade.php` (const baru + `hitungSubtotalLinkedBaris()` + fix regex).

**Verifikasi**: `tests/Feature/Tahap7/PreviewBumbuDanTotalHppParseTest.php` (8 test) — regex fix ada di markup, endpoint preview-bumbu hitung benar TANPA produk tersimpan (skenario Bug 1 asli), skip bahan mode gratis, tolak tanpa login (401)/tanpa permission (403), form Create pakai endpoint baru (bukan bergantung `KALKULATOR_URL`), regresi save produk dgn bumbu linked. Full regression 235 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟢 Addendum keempat — Auto-isi Satuan di Master Bumbu Pusat (2026-09-19)**: form "Tambah Bahan" (`resources/views/master/resep-bumbu/edit.blade.php`) dulu dropdown "Satuan" SELALU default ke opsi pertama ("kg") apapun bahan yang dipilih — user harus ingat ganti manual sesuai satuan asli bahan (mis. Kulit Dimsum = pcs), rawan salah pilih tanpa disadari (beda dari section Resep Produk Jual yang sudah auto-isi satuan sejak fitur Import Bumbu Pusat, [[4.16]]). Fix: opsi `<select id="itemIdSelect">` ditambah `data-satuan="{{ $b->satuan }}"`, JS baru `autoisiSatuan()` (listener `change` di `itemSelect`, terdaftar SEBELUM listener `updatePreview()` yang sudah ada, supaya `satuanEl.value` sudah ter-update duluan saat preview dihitung) meng-set dropdown Satuan begitu bahan dipilih. Karena dropdown Satuan cuma 4 opsi baku (`kg`/`g`/`ons`/`pcs`) sedangkan `Item.satuan` bebas teks ("gram", "ml", "buah", dst), ditambah `normalisasiSatuan()` yang memetakan variasi teks umum ke 4 opsi itu — **kalau satuan bahan tidak dikenali** (mis. "liter"/"ml", belum ada opsi volume di dropdown ini) fungsi return `null` dan dropdown DIBIARKAN apa adanya, tidak dipaksa ke nilai yang salah. Murni 1 file view, 0 perubahan controller/route/migration.

**Verifikasi**: `tests/Feature/Tahap7/AutoSatuanResepBumbuTest.php` (4 test) — `data-satuan` ada di markup, JS `autoisiSatuan()`/`normalisasiSatuan()` ter-render, variasi teks "gram"/"kg" ter-normalisasi, satuan tak dikenal ("liter") tidak dipaksa, regresi simpan bahan masih normal. Full regression 239 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.20 🔴 Error 500 "Tambah Transaksi" Pemasukan Kas Kategori "Saldo Awal" — ENUM DB Tidak Sinkron dengan Enum PHP (2026-09-19)

**Laporan Owner**: submit form "Tambah Transaksi" (menu Keuangan) — pemasukan, kategori "Saldo Awal", jumlah Rp1.000.000 — 500 error: `SQLSTATE[01000]: Data truncated for column 'kategori'`.

**Root cause**: `transaksi_keuangans.kategori` masih raw MySQL **ENUM** warisan Berkah Mulyo (`migration 2024_01_01_000015_create_keuangan_tables.php`) dengan **9 nilai TETAP** (`penjualan, jasa_giling, pembelian_bahan, gaji, sewa_gedung, penyusutan, operasional, pembelian_aset, lainnya`). Di level PHP, `App\Enums\KategoriTransaksi` sudah punya case ke-10 `SaldoAwal = 'saldo_awal'`, dan `KategoriTransaksi` (model, tabel `kategori_transaksis`, seeder) sudah punya row `kode='SALDO'` (`is_system=true`, tipe `pemasukan`, dipilih otomatis saat bikin Kas baru **ATAU** bisa dipilih manual di dropdown "Tambah Transaksi") — tapi kolom DB **TIDAK PERNAH ikut di-`ALTER`** saat case PHP itu ditambahkan. Insert dengan `kategori='saldo_awal'` MySQL truncate ke closest-match/kosong dan (karena `sql_mode` strict) Laravel melempar `QueryException`, bukan silent-fail. Bug ini **laten sejak lama**, baru kepicu begitu ada user genuinely memilih kategori "Saldo Awal" secara manual di dropdown (jalur create-Kas otomatis kemungkinan jarang dipakai / kasnya sudah lama ada duluan sebelum bug ini ketemu).

**Fix**: migration `2026_09_19_800001_widen_kategori_enum_in_transaksi_keuangans_table` — `ALTER TABLE ... MODIFY kategori ENUM(...9 value lama..., 'saldo_awal')`, pola sama seperti widen enum sebelumnya ([[4.17]] Gojek/Grab, migration loyalty `sumber_data`). Reversibel (`down()` migrasi data `saldo_awal`→`lainnya` dulu baru shrink ENUM, diverifikasi manual `migrate`→`rollback`→`migrate`). **Hanya 1 value yang kurang** — dikonfirmasi dari `KategoriTransaksi::toEnumValue()` (`app/Models/KategoriTransaksi.php`) yang memetakan PERSIS 9 kode lama + `SALDO`→`saldo_awal`, `default` jatuh ke `'lainnya'` untuk kode manapun di luar itu — jadi tidak ada kategori lain yang berpotensi kena bug serupa.

**Verifikasi**: `tests/Feature/Tahap7/KategoriSaldoAwalEnumFixTest.php` (4 test) — kolom DB benar sudah terima `'saldo_awal'`, submit manual "Tambah Transaksi" kategori Saldo Awal berhasil (reproduksi persis skenario Owner), jalur create-Kas otomatis dengan saldo awal tetap normal (regresi), kategori lama (operasional) masih bisa disimpan. Full regression 243 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.21 🟢 Modal "Tambah Kategori Baru" — Field "Kode Kategori" Frontend Bilang Opsional, Backend Sudah Wajib (2026-09-19)

**Laporan Owner**: modal "Tambah Kategori Baru" (menu Master Barang → Tambah Item) — field "Kode Kategori" tampil placeholder "(opsional)" tanpa tanda wajib, padahal harus diisi supaya kategori itu tampil di pilihan.

**Root cause**: `ItemController::storeKategori()` SUDAH LAMA mewajibkan `kode_kategori` (`'required|string|max:20|unique:...'`) — backend tidak pernah longgar. Yang salah murni UI: `item/create.blade.php` & `item/edit.blade.php` (2 file, modal sama persis dikopi ke keduanya) menampilkan placeholder "(opsional)" dan TIDAK ada atribut `required`/tanda bintang merah — front-end dan back-end tidak sinkron. Efeknya: user submit tanpa isi Kode, form redirect balik dengan error validasi yang membingungkan karena UI-nya bilang boleh dikosongkan.

**Fix**: murni UI, samakan pola dengan field "Nama Kategori" di sebelahnya (yang sudah benar) — tambah `<span class="text-danger">*</span>` di label + atribut `required` di input + ganti placeholder jadi cth: "CAT-001" (tanpa "(opsional)"), di KEDUA file. 0 perubahan controller/route/migration.

**Verifikasi**: `tests/Feature/Tahap7/KodeKategoriWajibTest.php` (4 test) — label+placeholder baru tampil di form Create & Edit, backend tetap menolak kode kosong (regresi validasi existing), kategori dengan kode lengkap tetap bisa disimpan. Full regression 247 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.22 🟢 Default Basis Program Loyalty: Kg Giling → Total Belanja (Rp) (2026-09-21)

**Laporan Owner**: form "Tambah Program Loyalty" — dropdown "Tipe Program" (Auto-Track) berlabel "kumulatif kg giling, otomatis", padahal D'mentai tidak pernah pakai satuan kg.

**Root cause**: `LoyaltyService::auto_track` sudah di-extend Tahap 7 ([[4.12]] B2) supaya support 3 basis (`orders.berat_daging_kg`/`orders.total_bayar`/`orders.count`) dan form `sumber_data` sudah default-select "Total Belanja (Rp)" — TAPI 3 lapis lain masih basis kg warisan Berkah Mulyo, tidak ikut disinkronkan saat widen enum dulu:
1. **DB column default**: `loyalty_programs.sumber_data`/`satuan_qty` masih `DEFAULT 'orders.berat_daging_kg'`/`'kg'` (migration awal `2026_08_11_000001`, tidak diubah saat widen `2026_09_17_600001`).
2. **Backend fallback**: `LoyaltyProgramController::store()` — `$validated['sumber_data'] ?? 'orders.berat_daging_kg'` (fallback kalau field tidak dikirim).
3. **Label statis di view**: dropdown "Tipe Program" (create & edit) hardcode "kumulatif kg giling, otomatis" + helper text sebut `orders.berat_daging_kg` eksplisit, TIDAK PEDULI basis apa yang sebenarnya dipilih di dropdown "Basis Perhitungan" terpisah.
4. **`loyalty-program/show.blade.php`**: info alert + tooltip kolom "Order Tanpa Data" hardcode teks "berat gilingan"/"kg" utk SEMUA program apapun basisnya — salah/membingungkan utk program Rp/transaksi (yang sekarang jadi default).

**Fix (sinkronisasi 4 lapis, opsi kg TETAP ADA sbg pilihan legacy — tidak dihapus, cuma bukan default)**:
1. Migration `2026_09_21_900001_ubah_default_sumber_data_loyalty_programs_ke_rp` — `ALTER ... MODIFY` DEFAULT kolom jadi `orders.total_bayar`/`Rp`. Reversibel, diverifikasi manual `migrate`→`rollback`→`migrate`.
2. `LoyaltyProgramController::store()` — fallback diubah ke `'orders.total_bayar'`.
3. `create.blade.php`/`edit.blade.php` — label "Auto-Track (kumulatif otomatis dari transaksi pelanggan)" + helper text generik (tidak hardcode kolom kg), default nama program & target diganti ke skenario Rp ("Hadiah Loyalty Pelanggan Setia", target Rp 500.000).
4. `show.blade.php` — info alert & tooltip "Order Tanpa Data" sekarang dinamis (`@php $sumberLabel`/`$tanpaDataLabel` di-`match()` dari `$loyaltyProgram->sumber_data`) — program basis kg (legacy) tetap tampil teks kg yang benar, program Rp/transaksi tampil teks yang sesuai.
5. `database/seeders/ProgramLoyaltySeeder.php` — demo seed diupdate ke basis Rp (**dead code**, tidak terdaftar di `DatabaseSeeder.php`, tidak pernah dieksekusi otomatis — disinkronkan isinya utk jaga-jaga kalau dijalankan manual suatu saat).

**Temuan terkait TIDAK diubah (di luar scope, murni informasi)**: `pelanggan/show.blade.php` (halaman detail 1 pelanggan) punya widget stat card "Total Kg Giling" (`PelangganController` query `SUM(berat_daging_kg)`) — SELALU tampil 0 kg utk semua pelanggan D'mentai (tidak ada order jasa giling sama sekali), dead-weight display warisan Berkah Mulyo. Tidak disentuh karena di luar scope "form Tambah Program Loyalty" yang diminta — perlu keputusan Owner terpisah apakah mau dihapus/diganti widget lain.

**File yang diedit**: migration baru, `LoyaltyProgramController.php`, `loyalty-program/{create,edit,show}.blade.php`, `ProgramLoyaltySeeder.php`.

**Verifikasi**: `tests/Feature/Tahap7/LoyaltyDefaultBasisRpTest.php` (8 test) — DB default sekarang Rp, submit tanpa `sumber_data` fallback ke Rp bukan kg, label create/edit tidak sebut "kg giling" lagi, show basis Rp tidak tampilkan teks "berat gilingan", show basis kg (legacy, kalau ada program lama) tetap tampil teks kg yang benar, regresi submit eksplisit basis kg & Rp keduanya tetap normal. Full regression 255 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**🟢 Lanjutan — Widget "Total Kg Giling" di Detail Pelanggan Diganti "Total Pembelian" (2026-09-21)**: temuan bonus dari audit [[4.22]] di atas — halaman detail pelanggan (menu Pelanggan → klik 1 pelanggan) punya 2 widget beda definisi: "Total Belanja" (`$pelanggan->orders()->sum('total_bayar')`, TANPA filter status/tanggal) dan "Total Kg Giling" (SELALU 0 utk semua pelanggan D'mentai — dead-weight display warisan Berkah Mulyo, tidak ada order jasa giling sama sekali). **Keputusan Owner: selaraskan jadi SATU widget** "Total Pembelian" (bukan tambah widget ke-3) — alasan: order POS D'mentai langsung lunas saat itu juga (tidak ada konsep "pending lama" yang butuh dibedakan dari "Total Belanja" kasar), 1 angka lebih jelas & maintainable.

- **Accessor baru** `Pelanggan::getTotalPembelianAttribute()` — `orders()->where('status','selesai')->whereDate('tanggal_order','<', hari_ini)->sum('total_bayar')`. Dipakai controller (`$pelanggan->total_pembelian`) menggantikan 2 query lama (`$totalBelanja` unfiltered + `$totalKgGiling` DB::table query) — `use Illuminate\Support\Facades\DB` di `PelangganController` ikut dihapus (sudah tidak dipakai lagi di controller itu).
- **View**: widget "Total Belanja"+"Total Kg Giling" (2 card) jadi 1 card "Total Pembelian" (icon `bi-cash-coin`, tetap warna hijau `border-success` existing). Widget "Rata-rata / Order" ikut pakai angka `$totalPembelian` yang baru (sengaja disederhanakan, bukan dipertahankan sbg 2 basis beda — konsisten keputusan "1 angka lebih jelas").
- **Bonus fix ditemukan saat audit "elemen lain masih pakai kg"**: section "Program Loyalty" di halaman yang SAMA (`pelanggan/show.blade.php:160`, terpisah dari `loyalty-program/show.blade.php`) ternyata PUNYA COPY TEKS hardcode "belum ada data berat gilingan (dihitung 0 kg)" yang SAMA PERSIS bug-nya dengan yang sudah difix di [[4.22]] tapi lolos karena beda file — sekarang ikut dibuat dinamis (`$progress['program']->sumber_data`/`satuan_qty`).

**File yang diedit**: `app/Models/Pelanggan.php` (accessor baru), `app/Http/Controllers/PelangganController.php` (pakai accessor, hapus 2 query lama + import `DB` tak terpakai), `resources/views/pelanggan/show.blade.php` (widget + teks loyalty dinamis). 0 migration (murni query+view).

**Verifikasi**: `tests/Feature/Tahap7/TotalPembelianPelangganTest.php` (9 test) — accessor: 0 tanpa transaksi, sum benar 3 transaksi, skip order pending-hari-ini (cuma hitung status selesai + tanggal<hari ini), skip order dibatalkan; rendering: widget baru tampil nilai benar, widget lama (Total Kg Giling/Total Belanja) sudah tidak ada, Rp 0 utk pelanggan baru, teks loyalty tidak hardcode "berat gilingan" utk basis Rp, regresi widget lain (Total Order/Order Terakhir/Rata-rata) masih normal. Full regression 264 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.23 🔴 Error 500 Export Excel "Laporan → Keuangan" — Enum Object-to-String + Excel Palsu (CSV Bertopeng .xls) (2026-09-21)

**Laporan Owner**: menu Laporan → Keuangan (`laporan.keuangan.laba-rugi`, BEDA dari "Kelola Kas & Transaksi → Laporan Keuangan" `keuangan.laporan` yang terpisah) — klik Export Excel **error**. Juga keluhan umum lintas banyak menu Laporan lain: "1 kolom excel banyak isi" saat dibuka.

**Root cause 1 (crash, spesifik Laporan Keuangan)**: `LaporanKeuanganController::labaRugi()`/`arusKas()` export manual pakai `fputcsv()` — data `$pemasukan`/`$pengeluaran` didapat dari `TransaksiKeuangan::selectRaw('kategori, SUM(jumlah) as total')->groupBy('kategori')->get()`. Eloquent **TETAP menerapkan cast model** (`'kategori' => App\Enums\KategoriTransaksi::class`) ke atribut `kategori` MESKIPUN datang dari `selectRaw()` (bukan select kolom biasa) — jadi `$p->kategori` adalah OBJEK enum PHP, bukan string. `fputcsv($file, [$p->kategori, ...])` mencoba string-cast objek itu → **fatal error** `Object of class App\Enums\KategoriTransaksi could not be converted to string`. Ini bug NYATA, direproduksi persis via test (bukan cuma "kurang rapi").

**Root cause 2 (keluhan "1 kolom banyak isi", pola sama di export-export lain)**: file yang di-generate SEBENARNYA teks CSV plain (`fputcsv` dgn delimiter `;`) yang dikasih ekstensi `.xls` + header `Content-Type: application/vnd.ms-excel` — BUKAN file Excel biner/xlsx sungguhan. Excel coba buka sbg file native, gagal, fallback parse sbg teks — kalau regional setting Windows si user pakai KOMA sbg list separator (bukan titik-koma), SEMUA kolom yang dipisah `;` gagal terdeteksi dan collapse jadi 1 kolom utuh. Pola "CSV-as-.xls" ini historically dipilih (lihat [[7.5]]) krn simpel tanpa dependency — tapi codebase SUDAH PUNYA solusi lebih baik yang proven jalan: `app/Exports/TransaksiKeuanganExport.php` (dipakai "Kelola Kas & Transaksi") pakai **maatwebsite/excel** (sudah ada di `composer.json`, dependency existing bukan baru) — xlsx biner sungguhan, universal dibuka Excel apapun localenya.

**Fix**: 2 Export class BARU (`app/Exports/LaporanLabaRugiExport.php`, `app/Exports/LaporanArusKasExport.php`), pola sama `TransaksiKeuanganExport` (`WithStyles` utk header bold+warna+auto-size kolom, kategori dipanggil `->label()`/`label_kategori` bukan objek mentah) — `LaporanKeuanganController` diubah pakai `Excel::download(...)`, 2 method private CSV manual (`exportLabaRugiExcel()`/`exportArusKasExcel()`) DIHAPUS total.

**Scope sesi ini SENGAJA dibatasi ke "Laporan → Keuangan" saja** (yang genuinely error) — Owner melaporkan daftar panjang 20+ menu Laporan lain dgn keluhan serupa ("Excel belum rapi"/"belum ada PDF"), TAPI itu backlog terpisah yang jauh lebih besar (each menu beda struktur data, sebagian belum py export sama sekali) — didaftarkan sbg TODO Sprint 3 ([[12.13]]), bukan dikerjakan sekaligus tanpa scoping/prioritas dari Owner.

**File yang diedit**: `app/Http/Controllers/LaporanKeuanganController.php` (pakai Excel facade, hapus 2 method CSV manual), 2 file baru di `app/Exports/`.

**Verifikasi**: `tests/Feature/Tahap7/LaporanKeuanganExcelFixTest.php` (7 test) — HTML render normal (laba-rugi & arus-kas), export Excel dgn kategori enum asli TIDAK CRASH (reproduksi persis bug Owner), export dgn kategori `saldo_awal` tidak regresi, Content-Type xlsx sungguhan (`spreadsheetml`), tolak tanpa permission (403), alias `harian()`/`pengeluaran()` masih normal. Full regression 271 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

### 4.24 🟢 Sprint 3 Batch 1a — Rapikan Excel + Tambah PDF: Laporan Penjualan & Setoran Kasir (2026-09-21)

**Konteks**: lanjutan [[12.13]] — 2 dari 5 menu Prioritas 1 ("dipakai harian"). Owner minta pattern KONSISTEN lintas semua menu Sprint 3 ke depan (bukan cuma 2 ini): Excel header bold+fill krim brand, auto-width, format Rupiah/tanggal Indonesia, footer "Dicetak oleh"; PDF logo D'mentai, judul besar, info filter, tabel border+zebra-stripe, footer nomor halaman.

**Foundation BARU (dipakai SEMUA menu Sprint 3 ke depan, bukan cuma 2 ini)**:
- `app/Exports/Concerns/HasLaporanStyles.php` (trait) — `styleHeaderRow()` (bold+fill `#FFF8E7`), `autoSizeAllColumns()`, `styleRupiahColumn()` (number-format mask `"Rp" #,##0` — angka TETAP numerik asli, cuma DITAMPILKAN dgn prefix, supaya kolom tetap bisa di-SUM/sort normal di Excel, BUKAN string manual), `footerDicetakOleh()`.
- `resources/views/laporan/pdf/layout.blade.php` — layout Blade `@extends`-able, header logo+judul+info-filter (pola sama `laporan/pdf/neraca.blade.php` yang sudah proven), CSS zebra-stripe + border tipis siap pakai (class `table.data`), footer nomor halaman via **CSS `position:fixed` + `counter(page)`/`counter(pages)`** (BUKAN teknik `<script type="text/php">` dompdf yang butuh `config/dompdf.php` `enable_php=true` — project SENGAJA `enable_php=false` demi keamanan, jadi dipilih pendekatan CSS murni yang tidak butuh ubah config sama sekali).

**Per menu**:
1. **Laporan Penjualan**: `LaporanPenjualanExport` (ganti `fputcsv()` manual) + `laporan/penjualan/pdf.blade.php` (portrait) + tombol Export PDF baru di view. Method private `exportExcel()` lama DIHAPUS.
2. **Laporan Setoran Kasir**: `LaporanSetoranKasirExport` + `laporan/setoran-kasir-pdf.blade.php` (portrait, row selisih≠0 di-highlight kuning/merah tipis sesuai tanda selisih) — PDF dipicu via query param BARU `?format=pdf` pada route `export` yang SAMA (bukan route terpisah), Excel tetap default kalau `format` tidak diisi.

**Bug regresi ditemukan & difix sekalian**: 1 test lama (`Tahap6\DashboardOwnerHttpTest::test_export_laporan_setoran_kasir_berhasil`) assert `Content-Type: application/vnd.ms-excel; charset=UTF-8` (header CSV lama) — di-update ke `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (xlsx asli), krn memang sengaja berubah oleh fix ini, bukan regresi tak terduga.

**File yang diedit**: `app/Exports/Concerns/HasLaporanStyles.php` (baru), `app/Exports/LaporanPenjualanExport.php` (baru), `app/Exports/LaporanSetoranKasirExport.php` (baru), `resources/views/laporan/pdf/layout.blade.php` (baru), `resources/views/laporan/penjualan/pdf.blade.php` (baru), `resources/views/laporan/setoran-kasir-pdf.blade.php` (baru), `LaporanPenjualanController.php`, `LaporanSetoranKasirController.php`, `laporan/penjualan/index.blade.php`, `laporan/setoran-kasir.blade.php`.

**Verifikasi**: `tests/Feature/Tahap7/LaporanBatch1aExportTest.php` (10 test) — Excel xlsx valid (`spreadsheetml`), PDF valid (`application/pdf`), filter tanggal+cabang+status sesuai, data kosong tetap generate tanpa error, tolak tanpa permission (403), regresi HTML kedua halaman masih normal. Full regression 281 test lintas Tahap 2.5/5/6/7 PASS (0 regresi, 1 assertion lama diupdate krn perubahan disengaja).

### 4.25 🟢 Sprint 3 Batch 1b+1c — Rapikan Excel + Tambah PDF: Setoran Harian, Stok (3 sub-view), Laba Rugi Produksi (2026-09-21)

**Konteks**: lanjutan [[4.24]] — sisa 3 dari 5 menu Prioritas 1. **2 blocker genuine ditemukan saat audit, DIKONFIRMASI ke Owner sebelum eksekusi** (bukan diasumsikan sendiri):

**Blocker 1 — Laporan Setoran Harian**: struktur data asli (`SetoranHarianService`) adalah Ringkasan+Breakdown+Detail utk 1 cabang/1 periode, BUKAN 1 baris per Tanggal+Cabang. Owner minta format export BARU yang lebih ringkas. **Keputusan Owner**: bangun agregasi BARU (1 baris per Tanggal+Cabang: Total Order, Total Pemasukan, Total Pengeluaran, Kas Bersih) **KHUSUS UNTUK EXPORT** — halaman `index()`/`print()` (dipakai closing kas harian) **SENGAJA TIDAK DIUBAH**, tetap multi-section seperti semula, karena kebutuhan export (rekap ringkas lintas hari/cabang) genuinely beda dari kebutuhan tampilan harian (detail 1 hari). Kolom **SENGAJA TIDAK dinamai "Total Setoran Kasir"** (rawan rancu dgn menu Laporan Setoran Kasir yang beda sumber data, [[4.24]]) — tombol export di-label ulang "Export Rekap (Excel/PDF)" biar jelas beda dari tampilan detail di halaman yang sama.

**Anti-double-count**: `Total Pemasukan` = `SUM(orders.total_bayar)` + `SUM(transaksi_keuangans WHERE tipe=pemasukan AND referensi_type != 'order')` — order OTOMATIS bikin `TransaksiKeuangan` ber-`referensi_type='order'` (`PenjualanService::buatOrder()`), jadi kalau di-SUM mentah dari `transaksi_keuangans` tanpa filter itu, omzet order ke-hitung DUA KALI. Diverifikasi eksplisit via test.

**Blocker 2 — Laporan Pergerakan Stok**: Owner minta kolom "Sisa Stok" (running balance) & "Referensi" (link ke order/adjustment/PO asal) — KEDUANYA belum ditrack sistem sama sekali (`stock_movements` cuma simpan qty movement itu sendiri + `catatan` freeform, tidak ada running-balance atau `referensi_type`/`referensi_id` terstruktur). **Keputusan Owner**: SKIP 2 kolom itu (bukan fitur export, tapi fitur tracking baru genuinely) — export pakai kolom yang SUDAH ada (Tanggal, Kode, Bahan, Cabang, Tipe, Qty, Satuan, Catatan), PDF dikasih info alert "Untuk stok saat ini, lihat Laporan Stok (Index)". Dicatat sbg TODO Sprint terpisah — lihat [[12.14]].

**Per menu**:
1. **Setoran Harian**: `LaporanRekapHarianExport` (FromCollection) + `laporan/setoran-harian/pdf.blade.php` (portrait, row Kas Bersih negatif = highlight merah). Method private `exportCsv()` lama DIHAPUS, diganti `buildRekapHarian()` (agregasi baru, private).
2. **Stok Index**: `LaporanStokExport` + `laporan/stok/pdf-index.blade.php` (portrait, row Habis=merah/Minim=kuning). Kolom Kode ditambah (`item->kode_item`, sebelumnya tidak ada di export lama).
3. **Stok Pergerakan**: `LaporanStokPergerakanExport` + `laporan/stok/pdf-pergerakan.blade.php` (**landscape**, kolom>7).
4. **Stok Minimum**: `LaporanStokMinimumExport` + `laporan/stok/pdf-minimum.blade.php` — menu ini SEBELUMNYA **tidak punya export sama sekali**, sekarang lengkap Excel+PDF dgn kolom BARU "Rekomendasi Beli" (`(qty_minimum - qty) × 1.2`, dibulatkan ke atas — dipakai bikin PO).
5. **Laba Rugi Produksi** (`LaporanLabaRugiController`, dikonfirmasi ULANG BEDA dari `LaporanKeuanganController::labaRugi()` [[4.24]] — beda sumber data, beda service, beda permission): `LaporanLabaRugiProduksiExport` (nama class SENGAJA dibedakan dari `LaporanLabaRugiExport` yang sudah dipakai controller Keuangan, utk hindari collision) — kolom dinamis 4 level (item/kategori/jenis_olahan/order), pola sama `LaporanLabaRugiExport` (`FromArray`, multi-section: Ringkasan+Breakdown+Detail). **2 varian PDF** sesuai request Owner: `pdf-ringkas.blade.php` (portrait, cuma Ringkasan — "biaya operasional"/"laba bersih" YANG DIMINTA OWNER TIDAK ADA di data source ini krn domain-nya murni produksi, bukan cash flow; diganti "Laba Kotor" + catatan eksplisit di PDF mengarahkan ke Laporan Keuangan utk biaya operasional) dan `pdf-detail.blade.php` (**landscape**, Breakdown+Detail lengkap).

**File yang diedit**: 5 Export class baru, 6 PDF view baru, `LaporanLabaRugiController.php`, `LaporanStokController.php`, `SetoranHarianController.php`, 5 index view (tombol export baru).

**Verifikasi**: `tests/Feature/Tahap7/LaporanBatch1bc1cExportTest.php` (20 test) — semua 5 menu Excel xlsx valid + PDF valid, filter periode/cabang diterapkan, anti-double-count pemasukan order (skenario eksplisit), data kosong tetap generate, 4 level breakdown Laba Rugi semua tidak crash (item/kategori/jenis_olahan/order × excel+pdf), konfirmasi 2 controller Laba Rugi genuinely beda, regresi HTML 5 halaman + tolak tanpa permission. Full regression 301 test lintas Tahap 2.5/5/6/7 PASS (0 regresi).

**Batch 1 (5 menu Prioritas 1) SELESAI SEMUA** — sisa 17 menu Laporan lain di [[12.13]] menyusul sbg Batch 2/3 terpisah.

---

## 5. STRATEGI PENGEMBANGAN

### 5.1 Modul yang DIMODIFIKASI/REPLACE 🔴

| Modul | Perlakuan | Alasan |
|-------|-----------|--------|
| **POS/Transaksi** | REPLACE (bikin baru) | Bisnis retail food beda total dari jasa giling |
| **Produk & Resep** | REPLACE | Fokus dimsum, bukan produk giling |
| **Master Item + Kategori** | REPLACE | Data dimsum, bukan daging |
| **Jenis Olahan** | REPLACE atau hapus | Konsep "olahan daging" tidak relevan |

### 5.2 Modul yang DIMODIF/ADD 🟡

| Modul | Perlakuan | Detail |
|-------|-----------|--------|
| **Setoran & Kas** | MODIFY | Tambah alur cabang→HO dengan approval |
| **Dashboard & Laporan** | MODIFY | Tambah info setoran & selisih |
| **BEP Otomatis** | EXTEND | Support `tipe_order='penjualan'` (bukan cuma `jasa_giling`) |
| **Loyalty Service** | EXTEND | `auto_track` support basis total belanja Rp / jumlah transaksi |
| **Panduan & Tooltip** | ADD | Untuk fitur baru saja |
| **Permission & Role** | ADD | Untuk menu baru saja |
| **Fitur Varian Produk** | ADD | Struktur N-dimensi baru (belum ada di Berkah Mulyo) |

### 5.3 Modul yang DIPAKAI APA ADANYA (REUSE) 🟢

| Modul | Alasan |
|-------|--------|
| **HR** (Karyawan, Absensi, Cuti, Gaji, Shift, Evaluasi) | Universal, pola sama untuk bisnis apapun |
| **Aset & Depresiasi** | Universal, bisnis butuh tracking aset |
| **Keuangan Dasar** (COA, Neraca, Kategori Transaksi) | Universal, akuntansi standar |
| **Stok FIFO + Batch** | Universal, pattern inventory food industry |
| **Notifikasi, Activity Log, Backup** | Infrastruktur, tidak perlu diubah |
| **Pembelian (PO)** | Universal untuk semua bisnis |
| **Transfer Antar Cabang** | Universal |

**Prinsip**: Kalau modulnya universal, JANGAN diubah. Fokus energi ke yang beda.

### 5.4 Roadmap 7 Tahap

| # | Tahap | Kompleksitas | Ketergantungan | Status |
|---|-------|--------------|-----------------|--------|
| 1 | **Branding** (warna, logo, nama, PWA icons) | Low | Independen | ✅ Selesai (2026-09-13) |
| 2 | **Master Data** (kategori, produk dimsum, resep, varian) | Medium | Bergantung keputusan varian (SUDAH TERJAWAB) | ✅ Selesai (2026-09-13) |
| 3 | **POS Modifikasi** (grid gambar, dine-in/takeaway/frozen, auto potong stok, varian) | HIGH | Bergantung Tahap 2 | ✅ Selesai (2026-09-13, dikoreksi: UI rollback ke Berkah Mulyo asli + modifikasi ringan — lihat catatan di bawah) |
| 4 | **Stok/Resep Adaptation** (rename `qty_per_kg` → `qty_per_unit`, resep basis "per porsi") | Medium | Bergantung Tahap 3 | ✅ Selesai (2026-09-13) |
| 2.5 | **Split Master Item** (Bahan Baku &amp; Kemasan vs Produk Jual, foto+resep+varian+outlet dalam 1 form) | High | Bergantung Tahap 2-4 | ✅ Selesai (2026-09-14) |
| 5 | **Setoran Kasir Cabang → HO** (submit auto-hitung, approve/reject, kas HO) | Medium | Independen (bisa paralel Tahap 3-4) | ✅ Selesai (2026-09-15) |
| 6 | **Dashboard & Laporan Owner** (widget setoran+kas HO, laporan Setoran Kasir) | Medium | Bergantung Tahap 5 | ✅ Selesai (2026-09-15) |
| 7 | **Final Polish & Go-Live** (cleanup Tailwind/Alpine, fix BEP+Loyalty hardcode, sinkron logo, error pages branded, E2E testing) | Medium-High | Bergantung SEMUA tahap | ✅ Selesai (2026-09-14) — lihat [[4.12]] |

### 5.5 Strategi Data Awal
- JANGAN langsung `migrate:fresh` — data HR dari Berkah Mulyo masih berguna sebagai template
- Bikin seeder baru khusus D'mentai untuk: outlet, produk, kategori, resep, varian
- Data lama Berkah Mulyo yang tidak relevan (produk giling) soft-delete bertahap sambil ganti dengan data D'mentai
- Nanti bikin tools "Reset Data D'mentai" di menu Settings untuk kemudahan reset ulang saat testing

---

## 6. DESAIN & BRANDING

### 6.1 Palette Warna
| Warna | Hex | Pakai untuk |
|-------|-----|-------------|
| **Hitam** | `#1A1A1A` | Sidebar, header, primary text, background utama |
| **Oranye** | `#FF6B00` | Button primary, accent, highlight, badge |
| **Krim** | `#FFF8E7` | Background section, card, hover state |
| Putih | `#FFFFFF` | Background section kontras |
| Abu terang | `#F5F5F5` | Divider, background secondary |

**Filosofi warna**: Hitam-oranye elegant + krim buat kesan hangat food-friendly. Sesuai dengan logo D'mentai.

### 6.2 Nama & Tagline
- **Nama**: **D'mentai**
- **Tagline**: **Dimsum & Gyoza**
- **APP_NAME** di `.env`: `"ERP D'mentai"`
- **Default `PengaturanUmum.nama_perusahaan`**: `"D'mentai"`
- **Tagline struk default**: `"Dimsum & Gyoza"` (bisa diedit per outlet)

### 6.3 Logo
| Versi | Fungsi | Sumber |
|-------|--------|--------|
| **Logo Full** (bulat, dengan text + tagline + mascot) | Login page, sidebar full, PDF header, PWA icon | File asli dari owner |
| **Logo Icon** (cuma mascot dimsum) | Sidebar collapsed, favicon, notif icon | Generate otomatis (crop mascot dari logo full) |

**PWA Icon**: Generate 8 ukuran (72, 96, 128, 144, 152, 192, 384, 512) dari logo full.

### 6.4 Responsif
- Wajib bisa dipakai di PC (browser desktop) dan Tablet
- Ikuti pola responsif Berkah Mulyo (Bootstrap 5 grid)
- POS khususnya: layout harus optimal di tablet horizontal (kasir pakai tablet)

---

## 7. SPESIFIKASI FITUR UTAMA

### 7.1 POS (Point of Sale)

**Layout**: Grid produk dengan gambar (bukan text list). Sidebar kanan untuk keranjang & bayar.

**Klasifikasi `items.tipe` (5 nilai, sejak Tahap 2.5 — 2026-09-14)**:
| Tipe | Tampil di POS? | Kelola via menu |
|------|----------------|-----------------|
| `bahan_baku` | Tidak (bahan mentah) | Bahan Baku & Kemasan |
| `kemasan` | Tidak | Bahan Baku & Kemasan |
| `tambahan_gratis` | Ya — section "Item Tambahan" (1-klik, gratis) | Bahan Baku & Kemasan |
| `produk_jual` | Ya — grid utama | Produk Jual |
| `produk_tambahan` | Ya — section "Item Tambahan" (berbayar) | Produk Jual |

Nilai lama `produk_jadi`/`lainnya` MASIH VALID di enum DB (backward compat, tidak dihapus) tapi tidak dipakai data manapun lagi setelah reklasifikasi 21 item dummy. Ketersediaan per outlet dikontrol tabel `item_cabang` (pivot item↔cabang, `harga_override` + `is_active`) — item TANPA row sama sekali dianggap aktif di semua cabang (fallback, lihat `Item::tersediaDiCabang()`).

**Fitur wajib**:
1. **Grid produk dengan gambar + nama + harga**
   - Filter kategori: "All Items", "Food", "Drink", "Frozen", dll (dinamis, bisa nambah dari admin)
   - Cari produk cepat (search bar)
2. **Tipe transaksi**: Dine-in / Takeaway / Frozen (dropdown atau tab)
   - Kalau Dine-in: bisa input nomor meja (optional, config per outlet)
   - Kalau Takeaway: mungkin ada fee (config)
   - Kalau Frozen: nanti bisa cetak tanggal produksi/expired di struk
3. **Varian produk** (N-dimensi)
   - Klik produk → muncul modal pilih varian (kalau ada)
   - Contoh: Dimsum Mentai → Size (S/M/L) + Level Pedas (1-5) + Saus (Mayo/Cheese)
   - Bisa juga tidak ada varian (produk simple)
   - Stok: config per produk (ikut induk atau terpisah per varian)
4. **Auto potong stok**
   - Stok dipotong SETELAH klik "Charge/Bayar" (bukan saat klik produk)
   - Produk yang bahan bakunya habis → di-disable dengan warning "Stok habis"
   - Produk lain yang stoknya masih ada tetap bisa dijual
   - Cek stok berdasarkan komposisi resep (per outlet)
5. **Resep opsional per produk**
   - Ada produk yang punya resep (auto potong komposisi seperti bumbu, kemasan, dll)
   - Ada produk yang tidak punya resep (potong 1 unit produk jadi aja)
6. **Item tambahan** (garpu, sumpit, saus, dll)
   - Bisa gratis (Rp 0) atau berbayar (misal Rp 100)
   - Ikut motong stok kalau bahan bakunya di-track
7. **Custom item** (input produk bebas di luar menu)
8. **Add customer** (untuk loyalty/riwayat pelanggan)
9. **Diskon & fee**
   - Diskon manual (kasir input %)
   - Diskon dari voucher/kode
   - Service charge (config per outlet)
   - Take away fee (config per outlet)
10. **Metode pembayaran**: Cash, QRIS, Transfer, Gojek, Grab (bisa split payment)
11. **Multi-action**: Save Bill (bill sementara), Print Bill (preview), Charge (bayar & selesai), Split Bill (bagi tagihan)
12. **Struk cetak**
    - Fleksibel: bisa cetak / tidak cetak / cetak 1-2 rangkap
    - Ikuti pola Berkah Mulyo (footer struk configurable per cabang)

**Prinsip UI/UX POS**:
- Layout harus lebih bagus & informatif dari referensi POS lain
- Optimasi untuk speed kasir (button besar, sedikit klik)
- Tampilan yang jelas: stok, keranjang, total

### 7.2 Struktur Varian Produk (BARU!)

**Model konseptual**:
```
Item (Produk)
  ├─ punya_varian: BOOLEAN (config per item)
  ├─ stok_per_varian: BOOLEAN (config per item)
  │
  └─ Kalau punya_varian = TRUE:
       ├─ ItemAttribute (misal: "Size", "Rasa", "Level Pedas")
       │    └─ ItemAttributeValue (misal Size: "S", "M", "L")
       └─ ItemVariant (kombinasi: Size M + Rasa Mentai + Level 3)
             ├─ harga_override (opsional)
             ├─ stok (kalau stok_per_varian = TRUE)
             └─ resep_override (opsional)
```

Ini fitur BARU (tidak ada di Berkah Mulyo). Perlu migration + model + controller + view.

### 7.3 Setoran Kasir Cabang → HO — ✅ Selesai (2026-09-15)

**Realisasi vs spesifikasi awal (2 penyesuaian disetujui Owner sebelum eksekusi)**:
- **Per HARI, bukan per shift** — tidak ada infrastruktur "buka/tutup shift kasir" di codebase ini (`shifts` murni jadwal HR). 1 baris `setorans` = 1 cabang + 1 tanggal (`unique(cabang_id, tanggal)`).
- **Hybrid dengan modul "Transfer Dana" (`SetoranController`, `setoran.*`) existing**: tabel BARU (`setorans`/`setoran_details`/`setoran_approvals`) untuk konsep "rekonsiliasi harian auto-hitung dari `order_payments`" (beda total dari Transfer Dana yang lump-sum manual) — tapi pergerakan uang saat approve REUSE pola 2-baris `transaksi_keuangans` (kategori `SETORKSR-OUT`/`SETORKSR-IN`, `kode_akun_coa=NULL` sama prinsip `SETOR-IN/OUT`), link via `referensi_type='setoran_kasir'`+`referensi_id` (BUKAN `setoran_pair_id`, supaya tidak nyerempet state machine Transfer Dana). Permission namespace `setoran_kasir.*` (beda dari `setoran.*`) supaya role yang sudah punya akses Transfer Dana tidak otomatis dapat akses modul baru.

**Alur final**:
1. Kasir buka `/setoran-kasir/create` — sistem auto-hitung breakdown per metode (tunai/transfer/qris/gojek/grab) dari `order_payments` hari itu (via `SetoranKasirService::hitungOtomatis()`)
2. **Scope sengaja dibatasi ke KAS TUNAI**: metode non-tunai sudah otomatis settle ke kas non-tunai cabang saat order dibuat (`PenjualanService`) — breakdown non-tunai di `setoran_details` murni informasi transparansi ke HO, TIDAK ada uang yang berpindah untuk metode itu lewat fitur ini
3. Kasir isi "Jumlah Uang Tunai yang Diserahkan" (default = sistem, bisa diedit kalau ada selisih fisik) + bukti foto opsional + catatan → submit → status `menunggu`
4. **Uang BELUM berpindah sama sekali saat submit** (beda dari Transfer Dana yang langsung potong kas pengirim) — `setorans` sebelum approve murni laporan, `Kas` tidak disentuh
5. HO (`admin_pusat`, permission `setoran_kasir.approve`/`.reject`) buka `/setoran-kasir` → Approve (kas cabang -X, kas HO +X, dalam 1 `DB::transaction()`) atau Reject (alasan wajib, kasir bisa revise — submit ulang tanggal yang sama meng-UPDATE baris existing in-place, bukan bikin baris baru, supaya tidak bentrok `unique(cabang_id,tanggal)`)

**Status**: `menunggu` 🟡 / `approved` 🟢 / `rejected` 🔴 (`App\Enums\StatusSetoranKasir`)

**Data tercatat**: `setorans` (tanggal, cabang, kasir submit, total sistem, total disetor, selisih, bukti foto, catatan kasir/HO, link ke 2 `TransaksiKeuangan` saat approved), `setoran_details` (breakdown per metode), `setoran_approvals` (audit trail submit/approve/reject — histori lengkap kalau ada revisi berkali-kali).

**Tabel DB baru**: `setorans`, `setoran_details`, `setoran_approvals` (lihat migration `2026_09_15_500001`-`500003`).

### 7.4 Dashboard Owner — ✅ Selesai (2026-09-15)

**Realisasi**: EXTEND `dashboard/pusat.blade.php` existing (bukan halaman baru) — Owner/admin_pusat sudah otomatis diarahkan ke situ, jadi widget baru langsung terlihat tanpa perlu 2 dashboard terpisah yang membingungkan. Semua widget baru di-gate 1 permission `dashboard.owner.view` (default: admin_pusat + Owner bypass).

**Baris 1 — Card Angka Besar** (persis 4 sesuai spesifikasi):
- Total Penjualan Hari Ini (semua outlet) — `SUM(orders.total_bayar)` hari ini, exclude Dibatalkan
- Total Penjualan Bulan Ini (sudah ada dari widget lama, dipertahankan apa adanya)
- Uang Belum Disetor — `SUM(setorans.total_disetor)` utk status `menunggu`+`rejected`. **Keterbatasan didisclose**: hari yang kasirnya BELUM SUBMIT setoran sama sekali tidak ikut terhitung (bukan bug kalkulasi — datanya memang belum ada untuk dihitung, itu soal kepatuhan submit kasir)
- Kas HO Saat Ini — `SUM(Kas.saldo_sekarang)` untuk cabang bertipe `gudang_pusat`

**Baris 2 — Grafik**: Trend penjualan harian 7 hari (line chart, semua outlet gabungan, widget BARU) + grafik bulanan 6-bulan-per-cabang (SUDAH ADA sebelumnya, dipertahankan — deliver "trend bulanan" dari spesifikasi, walau bukan 12 bulan persis). **Trend mingguan 4-minggu SENGAJA TIDAK dibuat** — dianggap redundant dengan trend harian 7-hari + bulanan 6-bulan yang sudah mencakup rentang pendek dan panjang; bisa ditambah nanti kalau genuinely dibutuhkan.

**Baris 3 — Tabel Alert** (3 tabel sesuai spesifikasi): Setoran Menunggu Approval (link "Proses" ke halaman detail approve/reject, BUKAN quick-action inline — konsisten pola "detail dulu baru aksi" yang sudah dipakai Transfer Dana), Outlet dengan Stok Minimum (item `bahan_baku`/`kemasan`/`produk_jual` di bawah `qty_minimum`, lintas cabang), Selisih Setoran (`ABS(selisih) >= Rp5.000`, threshold arbitrary tapi wajar untuk saring noise pembulatan).

**Filter tanggal/outlet/tipe transaksi dari spesifikasi awal SENGAJA TIDAK diimplementasikan** di widget dashboard (widget tetap fixed "hari ini"/"bulan ini"/real-time) — filter granular lebih cocok di halaman Laporan (`/laporan/setoran-kasir` sudah py filter tanggal+cabang+status) daripada dashboard ringkasan; menambah filter ke dashboard akan signifikan menambah kompleksitas widget tanpa manfaat sepadan untuk use-case "cek cepat kondisi hari ini".

**Bug lama dibersihkan sekalian** (ditemukan pas menyentuh `DashboardController::cabang()` untuk Tahap 6, CLAUDE.md 4.1): card "X order jasa giling hari ini" (hardcode `tipe_order='jasa_giling'`, selalu 0 untuk D'mentai) diganti breakdown **Order per Tipe Transaksi Hari Ini** (Dine-in/Takeaway/Frozen, dari `orders.tipe_transaksi` yang genuinely dipakai Tahap 3) — bukan expand scope, murni cleanup dead-weight widget yang kebetulan ada di file yang sama.

### 7.5 Laporan

**Status per jenis (Tahap 6, 2026-09-15)**:
1. Laporan Penjualan (harian/per outlet/per produk) — **SUDAH ADA sejak sebelumnya** (`LaporanPenjualanController`), tidak disentuh Tahap 6
2. **Laporan Setoran Kasir** — ✅ BARU (`/laporan/setoran-kasir`, permission `laporan.setoran_kasir.view`/`.export`) — filter tanggal+cabang+status, export Excel (CSV-as-.xls, pola sama `LaporanSetoranController` existing). **Beda dari `laporan.setoran` existing** yang melaporkan Transfer Dana generik (`SETOR-OUT`), bukan Setoran Kasir
3. Laporan Selisih — **digabung ke Laporan Setoran Kasir** (kolom `selisih` sudah tampil per baris + widget dashboard "Selisih Setoran vs Sistem"), TIDAK dibuat sebagai laporan/menu terpisah — datanya identik, memecah jadi 2 menu cuma menambah navigasi tanpa manfaat
4. Laporan Stok — **SUDAH ADA sejak sebelumnya**, tidak disentuh
5. Laporan Kas — **SUDAH ADA sejak sebelumnya** (`Kelola Kas & Transaksi`), tidak disentuh

**Export Laporan Setoran Kasir**: Excel ✅ (CSV-as-.xls). **PDF SENGAJA TIDAK dibuat** — `LaporanSetoranController` (rujukan pola terdekat, domain setoran yang sama) sendiri juga tidak punya export PDF, cuma Excel; menambahkan PDF khusus laporan ini akan jadi inkonsistensi pola dibanding laporan setoran lain, bukan penghematan scope yang genuinely dibutuhkan sekarang.

### 7.6 Hak Akses (Role-Based)

**Roles yang sudah ada** (dari Berkah Mulyo, tetap dipakai):
- `admin_pusat` — akses semua
- `admin_gudang` — HO/gudang
- `manajer_cabang` — 1 outlet
- `kasir` — POS + setoran only
- `operator_produksi` — produksi
- `helper` — akses terbatas

**Aturan menu**:
- Menu tampil di sidebar berdasarkan permission user
- Cek dulu permission sebelum tampilkan menu (`@can`)
- Jangan hide via CSS (harus di level backend)

### 7.7 Loyalty

**Untuk sekarang (Tahap awal)**:
- Pakai `event_based` (klaim manual berdasarkan pencapaian yang di-approve admin)
- `auto_track` DINONAKTIFKAN sementara (karena hardcode ke `jasa_giling`)

**Nanti (bisa di Tahap 6)**:
- Extend `LoyaltyService::auto_track` supaya support:
  - Basis "total belanja Rp"
  - Basis "jumlah transaksi"
  - Bukan cuma "berat kg" seperti Berkah Mulyo

---

## 8. KONVENSI CODING

### 8.1 Bahasa
- **Kode**: English (nama variable, function, class)
- **Comment**: Indonesian atau English (konsisten per file)
- **UI text**: Bahasa Indonesia (semua label, message, error)
- **Nama tabel & kolom**: mengikuti pola Berkah Mulyo (Indonesia, snake_case) — misal: `cabangs`, `karyawans`, `stok_masuk`

### 8.2 Naming
| Item | Convention | Contoh |
|------|-----------|--------|
| Model | PascalCase, singular | `Cabang`, `Karyawan`, `Setoran` |
| Controller | PascalCase + Controller | `PosController`, `SetoranController` |
| Table | snake_case, plural | `cabangs`, `karyawans`, `setorans` |
| Column | snake_case | `nama_produk`, `harga_jual` |
| Route | kebab-case | `/pos/kasir`, `/setoran/approve` |
| View | dot-notation, snake_case | `pos.kasir`, `setoran.index` |

### 8.3 Konvensi Rename (dari Berkah Mulyo → D'mentai)
| Kolom lama | Kolom baru | Alasan |
|-----------|-----------|--------|
| `qty_per_kg` (di `resep_bumbu_items`) | `qty_per_unit` | ✅ **SELESAI Tahap 4 (2026-09-13)** — kolom (raw `CHANGE COLUMN`, kompat MariaDB 10.4), accessor `getQtyPerUnitDalamKgAttribute()`, validasi, label UI, dan resep_bumbu sekarang terhubung langsung ke `item_id` (bukan cuma `jenis_olahan_id`) supaya POS otomatis temukan resep tanpa kasir pilih manual. |
| `berat_daging_kg` (di `order_items`) | (biarkan / `qty_pcs`) | Perlu diskusi lebih lanjut saat Tahap 3 |
| `tipe_order='jasa_giling'` | `tipe_order='penjualan'` | Dimsum tidak ada konsep "jasa giling" |
| `items.tipe='produk_jadi'` | `items.tipe='produk_jual'` | ✅ **SELESAI Tahap 2.5 (2026-09-14)** — 1:1 rename (extend enum, nilai lama tetap valid di DB tapi tidak dipakai data manapun lagi). `items.tipe='lainnya'` dipecah jadi `tambahan_gratis` (Garpu Plastik) + `produk_tambahan` (Saus Cabai Extra). Semua whitelist tipe yang dipotong-stok-langsung (`PenjualanService`, `Item::bisaDijualDiCabang()`) ikut di-update ke `produk_jual` — kalau lupa, item drink/frozen tanpa resep akan berhenti kepotong stoknya secara silent (bug nyata yang ditemukan & diperbaiki saat development Tahap 2.5). |

### 8.4 Struktur Folder (ikuti Berkah Mulyo)
```
app/
  Http/Controllers/       # semua controller
  Models/                 # semua model
  Services/               # business logic
  Traits/                 # HasAuditLog, HasCabang, FillsDeletedBy
  Observers/              # 12 observer untuk history/audit
  Enums/                  # RoleUser, dll
resources/views/
  layouts/                # template utama
  pos/                    # view POS
  setoran/                # view setoran
  dashboard/              # view dashboard
database/
  migrations/             # migration files
  seeders/                # seeder files
```

### 8.5 Pattern Coding yang Dipakai
- **Observer pattern**: 12 observer terdaftar di `AppServiceProvider` untuk history/audit
- **Trait `HasAuditLog`**: dipakai di 38 model — verified working
- **Trait `FillsDeletedBy`**: dipakai di 29 model — verified working
- **Gate pattern**: permission di-cache 1 jam (`all_permission_names`), delegasi ke `$user->hasPermission()`
- **DB::transaction()**: WAJIB untuk operasi multi-tabel

---

## 9. TESTING & QUALITY

### 9.1 Sebelum commit/lanjut fitur
- [ ] Semua migration jalan tanpa error (`php artisan migrate:status`)
- [ ] Semua seeder jalan tanpa error
- [ ] Login berhasil pakai user admin
- [ ] Menu terkait fitur baru tampil di sidebar (kalau permission benar)
- [ ] Cek `storage/logs/laravel.log` — tidak ada error baru
- [ ] Manual test alur end-to-end (misal: input transaksi POS → cek stok berkurang → cek masuk laporan)
- [ ] Filter cabang: pastikan query pakai `where('cabang_id', ...)` (SECURITY!)

### 9.1.1 Aturan Scope & Metode Test (WAJIB, sejak Tahap 2.5 — 2026-09-14)

**Scope — apa yang WAJIB ditest:**
1. Semua yang dibuat/diedit/diubah di sesi berjalan (controller/model/migration/view baru maupun diubah)
2. Fitur existing yang **berpotensi terdampak** oleh perubahan struktur (contoh nyata Tahap 2.5: ubah filter `items.tipe` di POS berdampak ke query grid, whitelist potong-stok di `PenjualanService`, bucket persediaan di `NeracaService`, dashboard/laporan Stok — SEMUA itu wajib ditest walau tidak "dibuat baru")

**TIDAK PERLU ditest:** modul yang genuinely tidak terhubung ke perubahan (contoh: ubah POS tidak perlu test menu HR/Aset/Karyawan/Cuti/Penggajian kecuali ada bukti keterkaitan nyata).

**Metode — WAJIB simulasi HTTP request beneran, BUKAN cuma Tinker:**
- Pakai Laravel Feature Test (`tests/Feature/...`) dengan `actingAs($user)->get(...)`/`post(...)`/`put(...)`/`delete(...)` + assertions (`assertOk`, `assertRedirect`, `assertSessionHasErrors`, `assertDatabaseHas`, `assertSoftDeleted`, dll) — bukan cuma `app()->call([...])` via Tinker (itu skip banyak middleware/pipeline nyata: `ConvertEmptyStringsToNull`, session, dll)
- Upload file: `UploadedFile::fake()->image(...)` / `->create(...)` + `Storage::fake('public')`
- Form kompleks (varian/resep terintegrasi): submit multi-field array persis seperti browser beneran akan kirim

**Database test — WAJIB pakai `erp_dimsum_test` (MySQL disposable), BUKAN sqlite in-memory default Laravel:**
- Codebase ini banyak migration raw MySQL-only (`ALTER TABLE ... MODIFY COLUMN ENUM`, dll) yang **tidak jalan di sqlite** — dikonfirmasi 5/5 test bawaan (`ExampleTest`/`ProfileTest`) sudah gagal total di `phpunit.xml` default sebelum Tahap 2.5 (`SQLSTATE[HY000]: ... near "MODIFY": syntax error`)
- Setup: `.env.testing` (`DB_CONNECTION=mysql`, `DB_DATABASE=erp_dimsum_test` — **database terpisah dari `erp_dimsum` dev**, dibuat sekali via `CREATE DATABASE erp_dimsum_test`), `phpunit.xml` TIDAK override `DB_CONNECTION`/`DB_DATABASE` lagi (supaya `.env.testing` yang dipakai)
- Migrate + seed SEKALI ke `erp_dimsum_test` (`APP_ENV=testing php artisan migrate --env=testing --force`, lalu seeder yang relevan) — bukan tiap test run, karena `RefreshDatabase` (migrate ulang tiap test class) lambat & migration DDL MySQL tidak transaction-safe untuk di-toggle bolak-balik
- Test class pakai trait `Illuminate\Foundation\Testing\DatabaseTransactions` (BUKAN `RefreshDatabase`) — tiap test method dibungkus 1 transaction & auto-rollback, data `erp_dimsum_test` tetap bersih antar test TANPA re-migrate
- **Reversibilitas migration** diverifikasi TERPISAH secara manual (`migrate` → `migrate:rollback --step=N` → `migrate` lagi, dicek langsung via `SHOW COLUMNS`/`SHOW TABLES`) — BUKAN di dalam automated test, karena DDL (`ALTER`/`CREATE`/`DROP TABLE`) auto-commit di MySQL sehingga tidak terlindungi oleh transaction rollback
- `UserSeeder` bawaan project py bug pre-existing (`cabang_id` kosong, tidak terkait Tahap 2.5) — test user dibuat langsung via `User::factory()->create(['role'=>...])`, bukan lewat seeder itu

**Kalau ada test FAILED:** STOP, jangan force lanjut ke fitur berikutnya — investigasi dulu apakah itu bug nyata (perbaiki) atau asumsi test yang salah (perbaiki test-nya), baru lanjut.

### 9.2 Bug Berkah Mulyo yang Sudah Difix di erp-dimsum
- Migration `kategori_transaksis` urutan salah → sudah di-fix (rename ke `000001`)
- Migration `fix_kas_soft_delete_unique_constraint` step 1 salah cek index → sudah di-fix (cek by name)

### 9.3 Backup Sebelum Perubahan Besar
Sebelum `migrate:fresh` atau operasi destruktif:
```bash
php artisan backup:run --only-db
```

---

## 10. TROUBLESHOOTING UMUM

### 10.1 Error saat migrate
- Foreign key gagal → cek urutan file migration, tabel yang dirujuk harus dibuat DULU
- Index/constraint conflict → cek migration `fix_*_soft_delete_unique_constraint`

### 10.2 Error saat artisan
- "Table 'cache' doesn't exist" → set `CACHE_STORE=file` di `.env`, jalankan `config:clear`
- ".env invalid" → biasanya `APP_NAME` yang ada spasi tapi tidak diapit `"..."`

### 10.3 Halaman blank / 500
- Cek `storage/logs/laravel.log`
- Cek `APP_DEBUG=true` di `.env` untuk lihat stack trace
- Cek permission folder `storage/` dan `bootstrap/cache/`

### 10.4 Menu tidak muncul
- Cek permission user (`users` → `role` → `role_permissions`)
- Cek blade sidebar (`@can` directive)
- Clear cache: `php artisan cache:clear`

### 10.5b Foto produk/upload tidak tampil (404) padahal sudah ke-upload
- Cek apakah `public/storage` beneran SYMLINK ke `storage/app/public` (`ls -la public/ | grep storage` harus tampil `storage -> ...`, bukan folder biasa)
- Kalau ternyata folder BIASA (bukan symlink) — pernah terjadi di environment ini (2026-09-15), kemungkinan `storage:link` sempat gagal jadi symlink di Windows dan malah bikin folder kosong — cek dulu isinya SAMA dengan `storage/app/public` (jangan asal hapus, pastikan tidak ada data unik yang cuma ada di situ), baru `rm -rf public/storage && php artisan storage:link`
- Setelah fix, `config:clear`+`view:clear`+`cache:clear`, lalu hard refresh browser (Ctrl+Shift+R) — foto lama sering ke-cache browser

### 10.5 Data cabang lain kelihatan (SECURITY BUG!)
- Cek query di controller → apakah ada `where('cabang_id', ...)`
- Jangan pakai `Model::all()` mentah untuk data cabang-spesifik
- CabangScope DORMANT — filter WAJIB manual

---

## 11. KONTAK & GAYA KOMUNIKASI

### 11.1 Yang Berhak Memutuskan
- **Owner project**: user (yang chat dengan Claude)
- Semua keputusan bisnis (fitur, alur, prioritas) harus konfirmasi dulu ke owner
- Jangan asumsi, tanya kalau ragu

### 11.2 Gaya Komunikasi
- Bahasa Indonesia (owner lebih nyaman)
- Step-by-step (owner belum expert developer)
- Jelaskan konteks setiap perubahan besar
- Kalau perlu keputusan, kasih opsi + pro/kontra
- JANGAN langsung eksekusi perubahan besar — konfirmasi dulu

---

## 12. TODO LIST (untuk masa depan)

### 12.1 Cleanup (Tahap 7) — ✅ Selesai (2026-09-14)
- [x] Hapus dead code Tailwind + Alpine dari `package.json`
- [x] Hapus `welcome.blade.php` yang tidak dipakai
- [x] Bersihkan Breeze scaffolding yang tidak dipakai (`layouts/navigation.blade.php`; register/profile dikonfirmasi BUKAN dead code, dibiarkan)
- [x] Bikin custom error page (403/404/500) dengan branding D'mentai

### 12.2 Perbaikan Bug Terwariskan — ✅ Selesai (2026-09-14)
- [x] Extend `BepOtomatisService` supaya support `tipe_order='penjualan'` — lihat [[4.12]]
- [x] Extend `LoyaltyService::auto_track` supaya support basis Rp/transaksi — lihat [[4.12]]
- [x] Sinkronkan 2 mekanisme logo (dinamis + statis) via `PengaturanUmumObserver` — lihat [[4.12]]

### 12.3 Fitur Baru yang Belum Ada di Berkah Mulyo
- [x] Struktur varian produk (N-dimensi) — ✅ Tahap 2 (DB+Model) + Tahap 2.5 (UI penuh di form Produk Jual, sync-safe utk histori order)
- [ ] Sistem shift kasir dengan buka-tutup shift (Setoran Kasir Tahap 5 sengaja per HARI, bukan per shift, karena ini belum ada)
- [x] Setoran cabang ke HO dengan approval workflow — ✅ Tahap 5 (Setoran Kasir, auto-hitung dari `order_payments`, scope kas tunai)
- [x] Dashboard owner dengan info setoran belum/sudah — ✅ Tahap 6 (4 card + trend 7 hari + 2 tabel alert di `dashboard/pusat.blade.php`)

### 12.4 Simplifikasi Disengaja di Tahap 2.5 (Kandidat Penyempurnaan Nanti)
- [ ] Preview kombinasi varian di form Produk Jual masih manual (tombol "Update Preview Kombinasi"), belum live-update tiap keystroke — cukup untuk kebutuhan sekarang, bisa di-upgrade ke reaktif kalau dirasa kurang nyaman dipakai kasir/admin
- [ ] `ItemVariant` yang di-toggle off lalu di-toggle-on lagi dgn kombinasi PERSIS sama akan membuat row BARU (bukan restore row lama yg soft-deleted) — desain sengaja simple demi keamanan data, efek sampingnya `item_variants` bisa sedikit menumpuk row trashed dari siklus tambah-hapus-tambah atribut yang sama berulang kali (harmless, tidak mengganggu fungsi)
- [ ] Kalkulator resep (estimasi HPP per N pcs produksi) di form Produk Jual pakai `harga_beli_terakhir` (snapshot terakhir), bukan FIFO batch cost aktual — cukup akurat utk preview kasar sebelum simpan, HPP order sungguhan tetap dihitung FIFO oleh `PenjualanService` seperti biasa

### 12.5 Panduan & Tooltip — ✅ Backfill Selesai (2026-09-16)
- [x] Panduan Master Bahan Baku, Master Produk Jual, Setoran Kasir, Dashboard Owner, Laporan Setoran Kasir, Pengaturan Umum (Branding) — 6 slug baru
- [x] Rewrite panduan `item-varian` (hapus "Coming Soon", tulis ulang sesuai UI live)
- [x] 20 tooltip baru, di-embed ke `<x-tooltip>` di view terkait (bukan cuma masuk DB)
- [x] Fix bug double-escape di komponen `tooltip.blade.php` (ditemukan saat backfill ini)
- [x] Embed tombol Cara Pakai (`<x-panduan-button>`) di 13 halaman fitur baru — gap ditemukan test manual SETELAH backfill konten selesai, ditutup di sesi terpisah (2026-09-16)
- [ ] Panduan untuk sistem shift kasir (belum relevan — fiturnya sendiri belum ada, lihat 12.3)

### 12.6 Bug Fix Ronde 2 — Temuan Test Manual Final — ✅ Selesai (2026-09-15)
- [x] Bug 1: Dropdown Cabang Aktif — root cause data (pivot cabang_user), bukan CSS — lihat [[4.13]]
- [x] Bug 2: Bill Tersimpan POS — posisi + batalkan (permission baru) + modal bayar lengkap — lihat [[4.13]]
- [x] Bug 3 🔴 KRITIS: Data Ghost Produk Jual — root cause nested `<form>` — lihat [[4.13]], [[4.14]]
- [x] Bug 4: Audit sync menyeluruh (187 route smoke test + guard rail nested-form + Data Terhapus coverage) — lihat [[4.13]]
- [x] Bug 5 (bonus): nested form di `notifikasi/index.blade.php` (silent failure "Tandai Semua Dibaca") — lihat [[4.13]], [[4.14]]
- [x] Bug 6 (bonus): crash 500 Laporan Eksekutif kalau cabang/periode tanpa penjualan (`margin_persen` null) — lihat [[4.13]]

**Tidak ada item yang di-DEFER** — ke-6 bug/temuan di ronde ini semuanya selesai difix + ditest di sesi yang sama, 0 TODO baru tersisa dari ronde ini.

### 12.7 Rename Jenis Menu & Master Bumbu Pusat — ✅ Selesai (2026-09-15)
- [x] Rename label "Jenis Olahan" → "Jenis Menu" (sidebar, halaman, Laporan Laba Rugi, tooltip, panduan) — lihat [[4.15]]
- [x] Rename label "Resep Bumbu Standar" → "Master Bumbu Pusat" + panduan ditulis ulang total (hapus deskripsi alur jasa-giling lama yang sudah mati) — lihat [[4.15]]
- [x] Kolom baru "Produk Terhubung" di tabel Master Bumbu Pusat (display-only, eager-load relasi `item` yang sudah ada)
- [x] Info box modal "Tambah Kategori Baru" (di menu Master Barang Lengkap) — jelaskan aturan visibility kategori di POS
- [x] ~~TODO: tombol "Import dari Bumbu Pusat" di form Produk Jual~~ — **SELESAI DIKERJAKAN 2026-09-17**, lihat [[4.16]]

### 12.8 Fitur Import dari Bumbu Pusat — ✅ Selesai (2026-09-17)
- [x] Migration: `resep_bumbu_items.item_id` nullable + kolom baru `resep_bumbu_ref_id` — lihat [[4.16]]
- [x] Modal picker (search + list Master Bumbu aktif) di form Produk Jual
- [x] HPP & potong stok live-calculated utk baris linked (auto-update tanpa cache/job)
- [x] Anti cyclic-reference by construction (1 level kedalaman maksimal)
- [x] Panduan `produk-jual` + tooltip baru + 21 test (feature+edge case+regresi), 182 test total lintas fase PASS

### 12.9 Improvement Test Manual Production: Rename Label + Hapus Gojek/Grab — ✅ Selesai (2026-09-18)
- [x] Rename label POS "Nama (jika tidak terdaftar)" → "Nama" — lihat [[4.17]]
- [x] Hapus `Gojek`/`Grab` dari `TipePembayaran` enum + migration incremental reversible (data-migrate + alter enum) — lihat [[4.17]]
- [x] Update validasi, breakdown Setoran Kasir, UI POS (~10 titik), UI Setoran Kasir, panduan (6 lokasi)
- [x] 12 test baru + 1 assertion test lama diupdate (perubahan disengaja), 194 test total lintas fase PASS

### 12.10 Fix Foto Produk Tidak Tampil di Production (Rumah Web) — ✅ Selesai (2026-09-16)
- [x] Root cause audit: `.htaccess` bawaan blokir `/storage/*` + URL foto hardcode `asset('storage/...)` — lihat [[4.18]]
- [x] Override `asset()` global via `App\Support\StorageAwareUrlGenerator` + `AppServiceProvider::register()`
- [x] Route baru `/asset/{path}` (`StorageAssetController`) stream file dari `storage/app/public/`
- [x] Script `public/clear-cache.php` utk deploy shared hosting tanpa terminal/SSH
- [x] 11 test baru, 205 test total lintas fase PASS (0 regresi, termasuk smoke-test 187 route)
- [ ] **TODO opsional (tidak dikerjakan, di luar scope)**: `.htaccess` bawaan (`RewriteRule ^storage/ - [L,NC]`) masih ada apa adanya — tidak berbahaya (cuma jadi dead-weight di production krn Root Cause B sudah dihindari via `/asset/` bukan `/storage/`), tapi kalau mau benar-benar rapi bisa ditambah `RewriteCond %{REQUEST_FILENAME} -f` di depan rule itu supaya cuma aktif kalau file/symlink beneran ada (self-healing utk kedua environment). Owner declined edit `.htaccess` production langsung (risk lebih tinggi, sulit diverifikasi tanpa akses server) demi solusi Approach B yang murni level aplikasi.

### 12.11 UI Preview "Harga Master" & "Subtotal" di Section Resep Produk Jual — ✅ Selesai (2026-09-19)
- [x] Kolom "Harga Master" + "Subtotal" (readonly, live JS preview, raw multiply tanpa konversi — sengaja, lihat [[4.19]])
- [x] Placeholder "— (lihat Simulasi Produksi)" utk baris resep ter-link ke Master Bumbu Pusat
- [x] Info alert `alert-warning` + link Master Bahan Baku; rename kolom "Harga" → "Mode Harga"
- [x] Fix bug Blade compile (`@json()` dgn ekspresi kompleks) — dipindah ke `@php` block, lihat [[4.19]]
- [x] 12 test baru, 217 test total lintas fase PASS (0 regresi)
- [x] **Bug Fix Ronde 3 (2026-09-19)**: Simulasi Produksi baca DB bukan form real-time (Bug A) + footer Total HPP hilang (Bug B) — lihat addendum [[4.19]]. 8 test baru, 225 test total lintas fase PASS (0 regresi).
- [x] **Simplifikasi Ronde 4 (2026-09-19)**: format qty tanpa titik-ribuan (`formatQtyInput`) + konsolidasi 2 mode Total HPP jadi 1 — lihat addendum kedua [[4.19]]. 4 test diupdate/ditambah, 227 test total lintas fase PASS.
- [x] **Bug Fix Ronde 5 (2026-09-19)**: subtotal Bumbu Pusat selalu "—" di halaman Create (endpoint `kalkulator-resep` butuh produk tersimpan, fix: endpoint baru `preview-bumbu/{bumbu}` decoupled dari Item) + Total HPP salah jumlah (`parseFloat("1.200")` dibaca 1.2 bukan 1200, fix: buang semua non-digit sebelum parse) — lihat addendum ketiga [[4.19]]. 8 test baru, 235 test total lintas fase PASS (0 regresi).
- [ ] **Ditemukan tapi belum difix (di luar scope eksplisit)**: `ResepBumbuItem::getTotalHargaMasterAttribute()` pakai `harga_jual` (harusnya `harga_beli_terakhir`) — kolom "Total /kg" di halaman edit Master Bumbu Pusat selalu Rp0 untuk bahan baku. Perlu keputusan Owner apakah masuk Sprint 2 ([[12.12]]) atau ditangani terpisah.

### 12.12 Sprint 2 (Belum Dikerjakan) — Konversi Satuan Foolproof di Kalkulator Resep

**Root cause** (temuan audit [[4.19]], BELUM difix): `MasterProdukJualController::kalkulatorResep()` mengalikan `qty_per_unit` mentah dgn `harga_beli_terakhir` (Rp/kg) tanpa konversi satuan (gram/ml/ons → kg) — beda dari `expandKeBahanMentah()` (dipakai POS checkout riil) yang SUDAH benar pakai `getQtyPerUnitDalamKgAttribute()`. Root cause fundamentalnya: satuan disimpan sebagai string bebas (`gram`, `ml`, `ons`, `kg`, `pcs`, dst) TANPA konsep "family" — tidak ada cara sistem tahu "gram" itu 1/1000 dari "kg" secara terstruktur, konversi cuma hardcode di 1 accessor.

**Rencana implementasi (Opsi A, ~4 jam estimasi)**:
1. Kolom baru NULLABLE `items.satuan_family` (enum: `berat`/`volume`/`pcs`/null) — nullable supaya data existing tidak perlu di-backfill paksa, default null = behavior lama (unchanged, backward compat).
2. Service baru `KonversiSatuanService` (atau extend `ResepBumbuItem` accessor existing) — mapping unit→base-unit per family (`berat`: gram/ons/kg → kg; `volume`: ml/liter → liter) dgn faktor konversi eksplisit, dipakai KEDUA jalur (`kalkulatorResep()` DAN `expandKeBahanMentah()`) supaya konsisten 1 sumber logic, tidak duplikat seperti sekarang.
3. `kalkulatorResep()` diubah pakai service ini utk hitung qty×harga (bukan raw multiply) — HPP preview jadi akurat sesuai contoh Owner (Isian Ayam 45rb/kg × 35gram = Rp1.575, bukan Rp1.575.000).
4. UI Master Bahan Baku: dropdown satuan dibatasi ke daftar per family (bukan free-text) utk item baru — data existing free-text tetap jalan apa adanya (tidak retroactive).

**Alasan defer**: data existing masih manageable secara manual (Owner bisa cek+benerin via UI), dan UI improvement Opsi B ([[12.11]]) sudah cukup utk kebutuhan jangka pendek (anomali data langsung kelihatan visual sebelum Simpan). Effort ~4 jam dianggap belum prioritas dibanding fitur/bug lain yang lebih mendesak per 2026-09-19.

### 12.13 Sprint 3 (Belum Dikerjakan) — Rapikan Export Excel + Tambah Export PDF di 20+ Menu Laporan

**Latar belakang** (laporan Owner 2026-09-21, lihat [[4.23]] utk 1 item yang SUDAH difix — "Laporan → Keuangan"): audit menyeluruh menu Laporan menemukan pola export Excel yang TIDAK KONSISTEN di seluruh project — sebagian pakai `Excel::download()` (maatwebsite/excel, xlsx sungguhan, RAPI), sebagian pakai `fputcsv()` manual dgn ekstensi `.xls` (CSV bertopeng, "1 kolom banyak isi" kalau locale Excel beda — root cause persis sama dgn [[4.23]]), dan banyak yang BELUM PUNYA export PDF sama sekali walau `barryvdh/laravel-dompdf` sudah jadi dependency project.

**Daftar lengkap dari Owner** (22 menu, per 2026-09-21):
| Menu | Excel | PDF |
|------|-------|-----|
| Penjualan | belum rapi | belum ada |
| Stok | belum rapi | belum ada |
| Keuangan (`laporan.keuangan.*`) | ✅ **SUDAH DIFIX** [[4.23]] | belum ada |
| HR / SDM | belum rapi | belum ada |
| Aset | belum rapi | belum ada |
| BEP | belum ada | belum ada |
| BEP Otomatis | belum ada | ✅ sudah baik |
| Transfer/Perpindahan Dana | belum rapi | belum ada |
| Per Kategori | belum rapi | belum ada |
| Audit Bukti | belum rapi | belum ada |
| Saldo Kas | belum rapi | belum ada |
| Setoran Harian | belum rapi | belum ada |
| Komisi Sales | belum rapi | belum ada |
| Laba Rugi (`laporan.laba-rugi.*`, BEDA dari Keuangan) | belum rapi | belum ada |
| Laba Rugi Formal | belum ada | ✅ sudah baik |
| Neraca | belum ada | ✅ sudah baik |
| Buku Besar | belum ada | belum ada |
| Laporan Eksekutif | belum ada | ✅ sudah baik |
| Simulator BEP | belum ada | belum ada |
| Analisa Jam Ramai | belum ada | belum ada |
| Pemakaian Perlengkapan | belum ada | belum ada |
| Laporan Setoran Kasir | belum rapi | belum ada |
| Cabang vs Cabang | belum rapi | belum ada |

**Kenapa DIDEFER (bukan dikerjakan sekaligus)**: scope terlalu besar utk 1 sesi (22 menu × 2 kemungkinan kerjaan = puluhan file controller+Export class+view baru), tiap menu struktur datanya beda (sebagian tabular sederhana cocok `WithMapping` biasa, sebagian multi-section kayak Laba Rugi butuh custom `FromArray` seperti [[4.23]], sebagian py grafik yang tidak relevan di Excel/PDF). **Rencana kerja**: pola yang SUDAH proven dari [[4.23]] (`TransaksiKeuanganExport`/`LaporanLabaRugiExport` sbg referensi) dipakai ulang per menu, dikerjakan bertahap per-batch (mis. per kelompok "Keuangan" dulu, lalu "Operasional", dst) dgn approval Owner tiap batch — BUKAN big-bang 1 commit raksasa yang susah di-review/di-test.

**Effort awal (rough estimate, perlu di-refine per batch)**: rata-rata ~30-45 menit/menu utk rapikan Excel (kalau struktur data mirip yang sudah ada), ~20-30 menit/menu tambahan utk PDF (kalau ada view print-friendly yang bisa direuse via `dompdf`) — total kasar 15-25 jam utk semua 22 menu, TIDAK termasuk waktu test tiap menu.

**✅ Batch 1 SELESAI SEMUA (2026-09-21)** — 5 dari 5 menu Prioritas 1 (Penjualan, Setoran Kasir [[4.24]]; Setoran Harian/Rekap, Stok 3 sub-view, Laba Rugi Produksi [[4.25]]). Sisa 17 menu Laporan lain (Prioritas 2/3) MENYUSUL sbg Batch 2/3 di sesi terpisah.

---

### 12.14 TODO Terpisah — Tracking Running-Balance & Referensi di `stock_movements`

**Latar belakang** (ditemukan saat Sprint 3 Batch 1b/1c, [[4.25]] Blocker 2): Owner minta kolom "Sisa Stok" (running balance setelah tiap movement) dan "Referensi" (link balik ke order/adjustment/PO yang menyebabkan movement itu) di Laporan Pergerakan Stok — KEDUANYA belum ditrack sistem. **SENGAJA DISKIP** dari Sprint 3 (bukan cuma rapikan export, tapi fitur tracking baru genuinely).

**Rencana implementasi (kalau nanti dikerjakan)**:
1. Migration tambah kolom `stock_movements.stok_sesudah` (snapshot qty SETELAH movement itu diterapkan — dicatat SEKALI saat movement dibuat, bukan dihitung ulang tiap kali dibaca, supaya histori tidak berubah kalau ada koreksi data lampau) + `referensi_type`/`referensi_id` (polymorphic, pola sama `transaksi_keuangans.referensi_type/id` yang sudah ada).
2. Backfill data historis: hitung running balance mundur dari `stocks.qty` SAAT INI dikurangi/ditambah tiap movement secara kronologis terbalik — perlu hati-hati kalau ada data movement yang hilang/tidak lengkap (kemungkinan besar ADA krn fitur ini baru ditambah belakangan).
3. Titik-titik yang perlu diisi `referensi_type`/`referensi_id` saat create movement: checkout POS (referensi ke `order`), penerimaan PO (`purchase_order`), adjustment manual (`stock_adjustment` kalau ada modelnya, atau `null` utk manual murni).
4. Update `LaporanStokPergerakanExport`/`pdf-pergerakan.blade.php` tambah 2 kolom ini setelah data tersedia.

**Alasan defer**: effort backfill data historis tidak trivial (tergantung kelengkapan data movement lama), dan Laporan Stok Index sudah cukup utk cek "stok sekarang" (Owner declined tambah kompleksitas di Sprint 3, cukup kasih info alert pengarah ke situ).

---

## 13. CHECKLIST SEBELUM MULAI FITUR BARU

Setiap kali mulai kerja fitur baru, Claude Code WAJIB:

- [ ] Baca ulang bagian relevan di file ini
- [ ] Cek pola serupa di codebase Berkah Mulyo (yang sudah dicopy)
- [ ] Konfirmasi scope & detail ke user (kalau belum jelas)
- [ ] Bikin plan (list file yang akan diubah/dibuat)
- [ ] Cek `AUDIT_SISTEM.md` bagian yang relevan
- [ ] Eksekusi dengan test bertahap
- [ ] Update section "Status" di roadmap (bagian 5.4)
- [ ] Tambah permission + panduan + tooltip untuk fitur baru
- [ ] Cek filter cabang manual di query (SECURITY!)
- [ ] Kabari user ketika selesai & minta test

---

## 14. REFERENSI FILE PENTING

| File | Isi |
|------|-----|
| `CLAUDE.md` (ini) | Single source of truth — visi, aturan, spesifikasi |
| `CLAUDE.berkahmulyo.backup.md` | Backup CLAUDE.md dari Berkah Mulyo (jangan dihapus, untuk referensi pola) |
| `AUDIT_SISTEM.md` | Audit lengkap sistem base (Berkah Mulyo) — modul, alur, gap |
| `.env` | Config lingkungan (APP_NAME, DB, dll) |
| `routes/web.php` | 817 baris route — peta URL sistem |
| `resources/views/layouts/app.blade.php` | Sidebar utama — peta menu sistem |
| `app/Providers/AppServiceProvider.php` | Observer + Gate + Recurring trigger |
| `bootstrap/app.php` | Middleware alias + routing config |

---

**🎉 PROJECT LIVE DI PRODUCTION** (https://erpdimsum.azwacore.com) **— Tahap 7 (Final Polish) selesai 2026-09-14, Bug Fix Ronde 2 selesai 2026-09-15, Rename Jenis Menu/Master Bumbu Pusat + Fitur Import dari Bumbu Pusat selesai 2026-09-17, Improvement Test Manual Production selesai 2026-09-18.** Semua 7 tahap roadmap tuntas + 6 bug dari test manual final (termasuk 1 KRITIS: data ghost akibat nested form) sudah difix & diguard-rail + fitur link-resep-antar-produk (anti cyclic by construction, live-calculation tanpa cache/job) sudah dibangun + POS disederhanakan jadi 3 metode pembayaran (Tunai/Transfer/QRIS, Gojek/Grab dihapus) berdasar masukan pemakaian nyata di production. 194 test lintas fase PASS (0 regresi), smoke test 187 halaman clean, dead code dibersihkan, bug BEP+Loyalty terwariskan sudah difix, error page branded, 4 alur bisnis end-to-end terverifikasi.

---

**Tahap 1 (Branding) selesai — 2026-09-13.** Nama, warna, logo, dan PWA icon sudah D'mentai. Siap eksekusi Tahap 2 - Master Data. 🚀
