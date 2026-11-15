<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Paket Modul, Masa Langganan & Tim IT (RANCANGAN_PAKET_LANGGANAN.md) —
 * panduan & tooltip. Idempotent (updateOrCreate / penanda).
 */
class PaketLanggananSeeder extends Seeder
{
    public function run(): void
    {
        Panduan::updateOrCreate(['slug' => 'tim-it'], [
            'judul' => 'Tim IT (akun penyedia aplikasi)', 'konten' => self::PANDUAN_TIM_IT, 'modul' => 'pengaturan', 'urutan' => 95, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'aktivitas-tim-it'], [
            'judul' => 'Aktivitas Tim IT', 'konten' => self::PANDUAN_AKTIVITAS, 'modul' => 'keamanan', 'urutan' => 5, 'aktif' => true,
        ]);
        Panduan::updateOrCreate(['slug' => 'paket-langganan'], [
            'judul' => 'Paket & Langganan', 'konten' => self::PANDUAN_PAKET, 'modul' => 'pengaturan', 'urutan' => 96, 'aktif' => true,
        ]);
        $role = Panduan::where('slug', 'role-hak-akses')->first();
        if ($role && ! str_contains((string) $role->konten, '<!-- PAKET-453 -->')) {
            $role->update(['konten' => rtrim((string) $role->konten) . "\n\n<!-- PAKET-453 -->\n## Izin yang tidak tampil\nCentangan untuk modul yang **tidak termasuk paket** aplikasi usaha Anda tidak ditampilkan di halaman ini, dan menunya tidak muncul untuk siapa pun (termasuk Owner). Centangan lama tetap tersimpan dan muncul lagi kalau modul itu diaktifkan oleh tim IT penyedia aplikasi.\n"]);
        }
        foreach ([
            ['paket.modul', 'Modul aktif', 'Modul yang boleh dipakai pelanggan ini. Modul mati: menu & izin hilang untuk semua user (termasuk Owner), data tetap aman.'],
            ['paket.aktif_sampai', 'Aktif sampai', 'Hari terakhir langganan berlaku. Kosong = tanpa batas. Sesudahnya masa tenggang, lalu baca-saja. Tim IT tidak terkena baca-saja.'],
        ] as $i => [$key, $title, $content]) {
            Tooltip::updateOrCreate(['key' => $key], ['title' => $title, 'content' => $content, 'modul' => 'pengaturan', 'aktif' => true, 'urutan' => 200 + $i]);
        }
        $this->command?->info('✅ Paket & Langganan / Tim IT: panduan & tooltip (idempotent).');
    }

    public const PANDUAN_TIM_IT = <<<'MARKDOWN'
## Tujuan
Akun **Tim IT** dipakai tim penyedia aplikasi untuk merawat dan memantau aplikasi pelanggan serta mengatur **Paket & Langganan**. Hak aksesnya penuh seperti Owner. Akun ini **tidak terlihat oleh pelanggan** (tidak ada di Manajemen User, Role & Hak Akses, atau pilihan role) dan tidak bisa diubah Owner.

## Langkah-langkah
1. Akun Tim IT pertama dibuat oleh skrip deploy dari isian `.env` server (`TEKNISI_NAMA`, `TEKNISI_EMAIL`, `TEKNISI_PASSWORD`). Hapus baris itu dari `.env` setelah deploy.
2. Login dengan akun itu, buka **Pengaturan → Tim IT**.
3. **Tambah** satu akun untuk setiap orang IT (nama, email, kata sandi minimal 12 karakter huruf + angka).
4. **Ganti kata sandi** atau **Nonaktifkan** akun orang yang sudah tidak bertugas.

## Catatan Penting
- Gunakan kata sandi **berbeda di setiap instalasi pelanggan**. Batas salah kata sandi tetap berlaku (dikunci sementara setelah beberapa kali salah).
- Semua login dan tindakan Tim IT tercatat di **Keamanan → Aktivitas Tim IT**, dan Owner pelanggan bisa melihatnya.
- Akun sendiri dan akun Tim IT aktif terakhir tidak bisa dinonaktifkan.
- Verifikasi dua langkah belum ada (ditunda) — jaga kata sandi baik-baik.
MARKDOWN;

    public const PANDUAN_PAKET = <<<'MARKDOWN'
## Tujuan
Menentukan **modul** yang boleh dipakai pelanggan ini sesuai kesepakatan pembayaran, dan **masa langganan**. Hanya akun Tim IT yang bisa membuka halaman ini.

## Langkah-langkah
1. Buka **Pengaturan → Paket & Langganan**.
2. Klik **Dasar**, **Menengah**, atau **Lengkap** untuk mengisi centangan cepat, lalu sesuaikan per modul kalau perlu (tampil **Kustom**). Modul yang dibutuhkan ikut menyala otomatis; mematikan modul ikut mematikan modul yang membutuhkannya.
3. Isi **Aktif sampai** (kosong = tanpa batas) dan **Masa tenggang** (bawaan 7 hari).
4. Klik **Simpan**.

## Akibat
- **Modul mati**: menu, tombol, entri Kamus, dan centangannya di Role & Hak Akses hilang untuk semua user, termasuk Owner. Alamatnya langsung menampilkan "Fitur … tidak termasuk paket Anda". Pekerjaan otomatis modul itu berhenti. **Data tidak dihapus.**
- **Masa langganan**: 14 hari sebelum berakhir → pita kuning untuk Owner. Lewat tanggal → pita merah untuk semua user, aplikasi masih normal selama masa tenggang. Lewat masa tenggang → **baca-saja**: login, melihat data, dan mengunduh laporan tetap bisa; simpan/ubah/hapus dan transaksi POS ditolak. Tim IT tidak terkena baca-saja.
- Semua perubahan tercatat di **Aktivitas Tim IT**.

## Catatan Penting
- Isi paket diberi **segel**. Kalau diubah langsung di database, segel tidak cocok: aplikasi tetap berjalan, tetapi Tim IT mendapat pita merah & notifikasi sampai paket disimpan ulang dari halaman ini.
- Instalasi lama otomatis **Lengkap tanpa batas tanggal** sampai diatur.
- Jangan mengganti `APP_KEY` di `.env` — segel ikut rusak.
MARKDOWN;

    public const PANDUAN_AKTIVITAS = <<<'MARKDOWN'
## Tujuan
Menunjukkan kepada Owner kapan tim IT penyedia aplikasi masuk ke aplikasi dan apa yang mereka lakukan — supaya pemeliharaan oleh pihak luar tetap transparan.

## Langkah-langkah
1. Buka **Keamanan → Aktivitas Tim IT**.
2. Pilih tanggal **Dari** – **Sampai** (bawaan 30 hari terakhir), klik **Tampilkan**.

## Isi catatan
- **Login**: waktu dan alamat IP.
- **Tindakan**: setiap simpan/ubah/hapus — nama menu, alamat halaman, metode, alamat IP. Isi formulir (mis. kata sandi) tidak disimpan.

## Catatan Penting
- Khusus Owner (dan Tim IT). Catatan tidak bisa dihapus dari halaman ini.
- Perubahan data yang lebih rinci (data lama → baru) tetap ada di **Audit Log**.
MARKDOWN;
}
