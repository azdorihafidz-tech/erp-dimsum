# ERP D'mentai

> Sistem ERP (Enterprise Resource Planning) untuk bisnis **D'mentai — Dimsum & Gyoza**.
> Mengelola operasional multi-outlet: POS, inventory, HR, keuangan, dan pelaporan.

**Versi**: 3.2  
**Status**: Production Ready  
**Tech Stack**: Laravel 12 · PHP 8.2 · MySQL · Bootstrap 5

---

## 📋 Overview

D'mentai ERP adalah sistem manajemen bisnis yang di-fork dari base ERP `erp-manajemenberkahmulyo` dan diadaptasi untuk kebutuhan bisnis retail food (dimsum & gyoza) dengan:

- **1 HO / Gudang Pusat** + **5 Outlet Retail**
- **Fleksibilitas**: struktur outlet, harga, produk, dan varian dapat dikustomisasi per lokasi tanpa perlu mengubah kode

---

## ✨ Fitur Utama

### 🥟 Point of Sale (POS)
- Grid produk dengan gambar (1-klik masuk keranjang)
- 3 tipe transaksi: **Dine-in**, **Takeaway**, **Frozen**
- Varian produk N-dimensi (Size + Rasa + Level Pedas)
- Split Payment multi-metode (Cash, QRIS, Transfer, Gojek, Grab)
- Save Bill + Batalkan + Ubah Metode Pembayaran
- Auto potong stok berdasarkan resep

### 📦 Master Data
- Master Bahan Baku & Kemasan
- Master Produk Jual (dengan resep terintegrasi + varian + kalkulator HPP)
- **Master Bumbu Pusat** — resep template reusable dengan **live HPP calculation**
- Upload foto produk + assign per outlet

### 💰 Setoran & Approval
- Auto-hitung total tunai harian dari POS
- Workflow: Kasir submit → HO approve/reject
- Pergerakan uang otomatis: Kas Cabang ↔ Kas HO

### 📊 Dashboard & Laporan
- Dashboard Owner real-time (4 card angka + grafik trend + tabel alert)
- Laporan Penjualan, Setoran, Selisih, Stok, Kas
- Export Excel + PDF

### 🎨 UX Polish
- Branding kustom D'mentai (hitam + oranye + krim + mascot)
- Panduan lengkap per menu (tombol "Cara Pakai" di setiap halaman)
- Tooltip di field-field penting
- Custom error pages (403/404/500)

### 🛡️ Fitur Umum
- Multi-cabang dengan permission per role
- HR (Karyawan, Absensi, Cuti, Penggajian, Evaluasi)
- Manajemen Aset & Depresiasi
- Break Even Point (BEP) Analysis
- Sistem Loyalty Membership
- Activity Log & Backup otomatis
- Notifikasi real-time (Pusher)

---

## 📊 Kualitas Kode

- ✅ **182 Automated Tests** (HTTP Feature Test)
- ✅ **580+ Assertions** dengan zero regresi
- ✅ **Guard rail**: Nested form scanner, Data Terhapus coverage (33 model)
- ✅ **Dokumentasi lengkap**: `CLAUDE.md` (v3.2) + `AUDIT_SISTEM.md`

---

## 🚀 Requirement

| Component | Min Version |
|-----------|-------------|
| PHP | 8.2+ |
| MySQL | 8.0+ / MariaDB 10.4+ |
| Composer | 2.x |
| Node.js | 18+ (untuk build assets, opsional) |

**PHP Extensions**: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, GD, Fileinfo

---

## ⚙️ Setup Lokal (Development)

### 1. Clone Repository
```bash
git clone https://github.com/azdorihafidz-tech/erp-dimsum.git
cd erp-dimsum
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`, sesuaikan konfigurasi database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_dimsum
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Setup Database
```bash
php artisan migrate --seed
```

### 5. Symbolic Link untuk Storage
```bash
php artisan storage:link
```

### 6. Jalankan Server Development
```bash
php artisan serve --port=8001
```

Buka browser: `http://localhost:8001`

---

## 🌐 Deployment ke Production

Deploy via cPanel Git Version (details ada di dokumentasi internal `CLAUDE.md`).

Environment production harus:
- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL=https://erpdimsum.azwacore.com`
- Update konfigurasi database ke server production

---

## 📁 Struktur Project

```
erp-dimsum/
├── app/
│   ├── Http/Controllers/     # 70+ controller
│   ├── Models/               # 68 model
│   ├── Services/             # 29 service
│   ├── Enums/                # Type-safe enum
│   ├── Observers/            # 12 observer
│   └── Traits/               # HasAuditLog, HasCabang, dll
├── database/
│   ├── migrations/           # 100+ migration
│   └── seeders/              # 34 seeder
├── resources/views/          # Blade templates (Bootstrap 5)
├── routes/web.php            # 800+ routes
└── tests/Feature/            # 182 automated test
```

---

## 🔒 Roles & Permissions

| Role | Akses |
|------|-------|
| `admin_pusat` | Full access (owner) |
| `admin_gudang` | HO / Gudang Pusat |
| `manajer_cabang` | 1 outlet spesifik |
| `kasir` | POS + Setoran only |
| `operator_produksi` | Produksi & stok |
| `helper` | Akses terbatas |

Permission dinamis via database (bukan hard-coded).

---

## 📚 Dokumentasi Internal

- **`CLAUDE.md`** — Blueprint lengkap project (aturan kerja, konvensi coding, spesifikasi fitur, temuan audit)
- **`AUDIT_SISTEM.md`** — Audit menyeluruh base ERP (20 section: A-T)
- **Panduan In-App** — Setiap menu punya tombol "Cara Pakai" dengan panduan step-by-step

---

## 🛠️ Development Workflow

Setiap fitur baru **WAJIB** include 4 komponen:
1. **Permission** — entry di `PermissionSeeder` + `RolePermissionSeeder`
2. **Panduan** — konten lengkap di `PanduanKontenSeeder`
3. **Tooltip** — di field-field penting (embed di view, bukan cuma isi DB)
4. **Tombol Cara Pakai** — `<x-panduan-button slug="{slug}" />` di halaman fitur

---

## 📝 License

**Proprietary** — Internal use only untuk D'mentai.  
Tidak untuk didistribusikan atau digunakan pihak lain tanpa izin.

---

## 👥 Kontak

**Owner**: D'mentai — Dimsum & Gyoza  
**Developer**: [azdorihafidz-tech](https://github.com/azdorihafidz-tech)

---

**Copyright © 2026 D'mentai. All rights reserved.**