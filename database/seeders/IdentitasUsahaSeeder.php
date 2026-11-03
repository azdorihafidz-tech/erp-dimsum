<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * Panduan + tooltip Identitas Usaha (CLAUDE.md 4.44 Poin 3, 2026-11-03).
 * Menulis ulang panduan `pengaturan-umum` (isi lama menyebut ikon PWA harus
 * dibuat developer — sekarang otomatis) + 3 tooltip baru. Idempotent
 * (updateOrCreate) — aman dijalankan ulang di production.
 */
class IdentitasUsahaSeeder extends Seeder
{
    public function run(): void
    {
        Panduan::updateOrCreate(['slug' => 'pengaturan-umum'], [
            'judul'  => 'Pengaturan Umum (Identitas Usaha)',
            'konten' => <<<'MARKDOWN'
## Tujuan
Mengatur identitas usaha — nama, nama singkat, tagline, telepon, dan logo — yang tampil di halaman login, sidebar, judul tab browser, struk, laporan PDF, slip gaji, TV Antrian, ikon aplikasi (PWA), dan aplikasi kasir di tablet.

Program yang sama bisa dipakai usaha lain: cukup ganti isian di halaman ini, tidak perlu mengubah program.

## Langkah-langkah

1. Buka menu **Pengaturan → Umum** (khusus Owner)
2. Isi/ubah:
   - **Nama Usaha** — nama lengkap (contoh: "D'mentai Indonesia"). Tampil di TV Antrian dan aplikasi kasir.
   - **Nama Singkat** — nama pendek untuk tempat sempit: sidebar, judul tab, struk, header laporan PDF (contoh: "D'mentai"). Kosong = pakai Nama Usaha.
   - **Tagline** — kalimat pendek di bawah nama (contoh: "Dimsum & Gyoza"). Kosong = tidak ditampilkan.
   - **Alamat** dan **No. Telepon / WhatsApp** — telepon tampil di header laporan PDF.
   - **Logo** — upload PNG/JPG/WebP maks 2 MB. Paling bagus PNG latar transparan, bentuk persegi atau bulat.
3. Klik **Simpan Pengaturan**
4. Perubahan langsung tampil di semua halaman setelah halaman dibuka ulang

## Logo & Ikon Aplikasi

- Saat logo baru di-upload, sistem otomatis membuat **ikon persegi** untuk tab browser (favicon), ikon aplikasi saat di-install ke HP/tablet (PWA), dan aplikasi kasir.
- Kotak kecil di sebelah logo menunjukkan ikon yang sedang dipakai.
- Centang **Hapus logo ini** untuk kembali ke logo bawaan program.

## Catatan Penting

- Pengaturan ini berlaku untuk SEMUA cabang. Footer struk per cabang diatur terpisah di **Cabang → Edit Cabang**.
- Struk yang sudah dicetak tidak berubah; struk berikutnya memakai nama baru.
- Ikon aplikasi yang sudah ter-install di HP kadang baru berubah setelah aplikasi di-install ulang (keterbatasan HP, bukan program).

## Troubleshooting

- **Logo tidak berubah setelah upload?** Tekan Ctrl+Shift+R (refresh tanpa cache).
- **Muncul "Logo gagal diproses"?** File mungkin rusak atau bukan gambar. Coba simpan ulang sebagai PNG lalu upload lagi.
MARKDOWN
            ,
            'modul'  => 'lainnya',
            'urutan' => 50,
            'aktif'  => true,
        ]);

        $tooltips = [
            ['key' => 'pengaturan_umum.nama_singkat', 'title' => 'Nama Singkat',
                'content' => 'Nama pendek untuk sidebar, judul tab, struk, dan header laporan PDF. Kosongkan untuk memakai Nama Usaha.'],
            ['key' => 'pengaturan_umum.tagline', 'title' => 'Tagline',
                'content' => 'Kalimat pendek di bawah nama usaha (sidebar, struk, laporan PDF). Kosongkan kalau tidak perlu.'],
            ['key' => 'pengaturan_umum.logo', 'title' => 'Logo Usaha',
                'content' => 'Tampil di login, sidebar, laporan PDF, dan TV Antrian. Ikon tab browser & aplikasi dibuat otomatis dari logo ini.'],
        ];

        foreach ($tooltips as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title'   => $t['title'],
                'content' => $t['content'],
                'modul'   => 'pengaturan',
                'aktif'   => true,
                'urutan'  => $i + 2,
            ]);
        }

        $this->command?->info('✅ Panduan & tooltip Identitas Usaha tersimpan (idempotent).');
    }
}
