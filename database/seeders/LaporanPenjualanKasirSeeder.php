<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 4.46 — Laporan di menu Penjualan yang bisa dibuka kasir (Transaksi, Produk,
 * Kategori) + izin terpisah untuk melihat KEUNTUNGAN (kasir tidak boleh).
 *
 * ADDITIVE & idempotent — aman di production: hanya menambah permission dan
 * mencentangnya (insertOrIgnore). Izin lihat laporan diberikan ke role yang
 * SUDAH bisa membuka Riwayat Order (`order.view`, termasuk role buatan) +
 * default; izin keuntungan HANYA ke admin_pusat & manajer_cabang (Owner lewat
 * Gate::before). Mapping role lain tidak disentuh.
 */
class LaporanPenjualanKasirSeeder extends Seeder
{
    public const PERMISSIONS = [
        ['name' => 'penjualan.laporan_transaksi.view', 'display_name' => 'Laporan Transaksi (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan_produk.view',    'display_name' => 'Laporan Penjualan Produk (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan_kategori.view',  'display_name' => 'Laporan Penjualan Kategori (menu Penjualan)', 'group' => 'penjualan'],
        ['name' => 'penjualan.laporan.keuntungan',     'display_name' => 'Lihat Keuntungan di Laporan Penjualan (Transaksi/Produk/Kategori)', 'group' => 'penjualan'],
    ];

    public const IZIN_LIHAT = [
        'penjualan.laporan_transaksi.view',
        'penjualan.laporan_produk.view',
        'penjualan.laporan_kategori.view',
    ];

    public const IZIN_KEUNTUNGAN = 'penjualan.laporan.keuntungan';

    /** Default mapping instalasi baru — sama dengan entry di RolePermissionSeeder. */
    public const ROLE_DEFAULTS = [
        'admin_pusat'    => [...self::IZIN_LIHAT, self::IZIN_KEUNTUNGAN],
        'manajer_cabang' => [...self::IZIN_LIHAT, self::IZIN_KEUNTUNGAN],
        'kasir'          => self::IZIN_LIHAT,
    ];

    public function run(): void
    {
        $ids = [];
        foreach (self::PERMISSIONS as $p) {
            $ids[$p['name']] = Permission::firstOrCreate(
                ['name' => $p['name']],
                ['display_name' => $p['display_name'], 'group' => $p['group']]
            )->id;
        }

        // Role yang sudah bisa membuka Riwayat Order ikut mendapat izin lihat laporan
        $rolePunyaOrder = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('permissions.name', 'order.view')
            ->pluck('role_permissions.role');

        $baris = [];
        foreach ($rolePunyaOrder as $role) {
            foreach (self::IZIN_LIHAT as $izin) {
                $baris[] = ['role' => $role, 'permission_id' => $ids[$izin]];
            }
        }
        foreach (self::ROLE_DEFAULTS as $role => $izinList) {
            foreach ($izinList as $izin) {
                $baris[] = ['role' => $role, 'permission_id' => $ids[$izin]];
            }
        }
        DB::table('role_permissions')->insertOrIgnore($baris);

        $roles = collect($baris)->pluck('role')->unique()->values();
        Cache::forget('all_permission_names');
        foreach ($roles as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        $this->panduanTransaksi();
        $this->tooltip();

        $this->command?->info('✅ Laporan Penjualan (kasir): izin lihat untuk ' . $roles->implode(', ')
            . '; izin keuntungan untuk ' . implode(', ', array_keys(array_filter(
                self::ROLE_DEFAULTS, fn ($l) => in_array(self::IZIN_KEUNTUNGAN, $l, true)
            ))) . ' (idempotent).');
    }

    private function panduanTransaksi(): void
    {
        Panduan::updateOrCreate(['slug' => 'laporan-transaksi-kasir'], [
            'judul'  => 'Laporan Transaksi (menu Penjualan)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Melihat jumlah transaksi dan pendapatan toko — per tahun, per bulan, per hari, sampai ke setiap transaksi dan struknya. Menu ini ada di bagian **Penjualan** dan bisa dibuka kasir.

## Langkah-langkah

### 1. Pilih periode
Di bagian atas ada 4 tombol:
- **Hari ini** → langsung daftar transaksi hari ini + grafik per jam.
- **Bulan ini** → grafik dan rincian per hari di bulan ini.
- **Tahun ini** → grafik dan rincian per bulan di tahun ini.
- **Selama ini** → grafik dan rincian per tahun sejak transaksi pertama.

Untuk melihat tanggal tertentu, isi kotak **Lihat tanggal** di sebelah kanan.

### 2. Klik untuk melihat lebih rinci
Klik **batang grafik** atau **baris tabel** untuk turun satu tingkat:

**Tahun → Bulan → Hari → Daftar transaksi → Struk**

Contoh: pilih **Tahun ini** → klik **Oktober** → muncul rincian setiap hari di bulan Oktober → klik **15 Oktober** → muncul semua transaksi tanggal itu → klik salah satu transaksi → struknya tampil (bisa dicetak ulang).

### 3. Kembali ke tingkat sebelumnya
- Tombol **Kembali** naik satu tingkat (mis. dari 15 Oktober ke Oktober).
- Atau klik jejak halaman di sebelahnya (mis. **Selama ini › 2026 › Oktober › 15 Oktober**) untuk langsung lompat ke tingkat mana pun.

### 4. Export
Tombol **Export Excel** dan **Export PDF** menyimpan isi halaman yang sedang dibuka. Contoh: sedang membuka Oktober → file berisi rincian per hari bulan Oktober.

## Arti angka
- **Jumlah Transaksi** — banyaknya order yang sudah dibayar.
- **Pendapatan** — uang yang dibayar pelanggan, sudah dikurangi diskon dan sudah termasuk service charge serta take away fee.
- **Keuntungan** — pendapatan dikurangi modal bahan (HPP) setiap order. Gaji, sewa, dan biaya operasional lain tidak ikut dikurangi (lihat Laporan Keuangan untuk itu).

## Catatan Penting
- Kotak, kolom, dan grafik **Keuntungan** hanya tampil untuk role yang punya izin **Lihat Keuntungan di Laporan Penjualan** (bawaan: Admin Pusat dan Manajer Cabang; Owner selalu bisa). Kasir hanya melihat jumlah transaksi dan pendapatan — termasuk di file Excel/PDF.
- Data mengikuti **cabang yang dipilih di pojok kanan atas**. Kasir selalu melihat cabangnya sendiri. Pilihan **Semua Cabang** menggabungkan semua cabang.
- Transaksi yang **dibatalkan**, **dihapus**, atau **bill tersimpan yang belum dibayar** tidak dihitung.
- Jam transaksi = jam order dibuat di POS.

## Troubleshooting
- **Menu Laporan Transaksi tidak ada di sidebar?** Minta Owner mencentang **Laporan Transaksi (menu Penjualan)** di Role & Hak Akses.
- **Angka beda dengan Laporan Laba Rugi?** Laba Rugi menghitung omzet dari harga barang sebelum diskon order dan tanpa service charge/take away fee; laporan ini memakai uang yang benar-benar dibayar pelanggan.
- **Transaksi tidak muncul?** Cek cabang di pojok kanan atas dan pastikan transaksinya sudah dibayar (bukan masih bill tersimpan).
MARKDOWN
            ,
            'modul'  => 'penjualan',
            'urutan' => 70,
            'aktif'  => true,
        ]);
    }

    private function tooltip(): void
    {
        $tooltips = [
            ['key' => 'laporan_transaksi_kasir.pendapatan', 'title' => 'Pendapatan',
                'content' => 'Uang yang dibayar pelanggan: sudah dikurangi diskon, termasuk service charge dan take away fee. Transaksi dibatalkan, dihapus, atau bill belum dibayar tidak dihitung.'],
            ['key' => 'laporan_transaksi_kasir.keuntungan', 'title' => 'Keuntungan',
                'content' => 'Pendapatan dikurangi modal bahan (HPP) setiap order. Gaji, sewa, dan biaya operasional lain tidak ikut dikurangi.'],
            ['key' => 'laporan_transaksi_kasir.tingkat', 'title' => 'Klik untuk rincian',
                'content' => 'Klik batang grafik atau baris tabel untuk turun: tahun → bulan → hari → transaksi → struk. Tombol Kembali naik satu tingkat.'],
        ];

        foreach ($tooltips as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title'   => $t['title'],
                'content' => $t['content'],
                'modul'   => 'penjualan',
                'aktif'   => true,
                'urutan'  => 70 + $i,
            ]);
        }
    }
}
