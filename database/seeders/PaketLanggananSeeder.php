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
