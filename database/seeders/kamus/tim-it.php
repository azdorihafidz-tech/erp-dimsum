<?php

/**
 * Kamus Menu — Tim IT, Aktivitas Tim IT, Paket & Langganan
 * (RANCANGAN_PAKET_LANGGANAN.md). Entri ber-izin '@teknisi' hanya terlihat oleh Tim IT.
 */
return [
    [
        'slug' => 'tim-it', 'judul' => 'Tim IT', 'kelompok' => 'Pengaturan', 'urutan' => 90,
        'letak' => 'Pengaturan → Tim IT (hanya terlihat oleh akun Tim IT)',
        'ringkasan' => 'Mengelola akun tim IT penyedia aplikasi: tambah, ganti kata sandi, aktif/nonaktif. Akun ini tersembunyi dari pelanggan.',
        'izin' => ['@teknisi'],
        'route_names' => ['tim-it.*'],
        'terkait' => ['aktivitas-tim-it', 'manajemen-user'],
        'kata_kunci' => 'tim it teknisi akun teknisi admin aplikasi penyedia aplikasi vendor maintenance support akun rahasia',
        'konten' => <<<'MD'
## Fungsi
Akun **Tim IT** (role Teknisi) dipakai tim penyedia aplikasi untuk perawatan, pemantauan, dan mengatur paket pelanggan. Hak aksesnya penuh seperti Owner, ditambah menu **Tim IT** dan **Paket & Langganan**.

## Supaya menu ini muncul & bisa dipakai
- Hanya untuk akun ber-role **Tim IT**. Owner pelanggan tidak melihat menu ini dan tidak melihat akun Tim IT di Manajemen User.
- Akun Tim IT pertama dibuat lewat skrip deploy (dari isian `.env` server). Akun berikutnya ditambah di halaman ini.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tambah | Akun Tim IT baru, langsung aktif. Kata sandi minimal 12 karakter (huruf + angka) |
| Ganti kata sandi | Kata sandi akun itu diganti |
| Nonaktifkan / Aktifkan | Akun tidak bisa / bisa login lagi. Akun sendiri dan akun aktif terakhir tidak bisa dinonaktifkan |

Semua tindakan di atas (dan semua tindakan Tim IT lain) tercatat di **Aktivitas Tim IT** yang bisa dilihat Owner.

## Wajib
- Satu akun per orang IT, kata sandi **berbeda di setiap instalasi pelanggan**.

## Jangan
- Jangan memakai satu akun bersama untuk beberapa orang — catatan aktivitas jadi tidak jelas siapa yang melakukan.
MD,
    ],

    [
        'slug' => 'aktivitas-tim-it', 'judul' => 'Aktivitas Tim IT', 'kelompok' => 'Keamanan', 'urutan' => 5,
        'letak' => 'Keamanan → Aktivitas Tim IT',
        'ringkasan' => 'Catatan setiap login dan setiap simpan/ubah/hapus yang dilakukan tim IT penyedia aplikasi di usaha Anda. Tidak bisa dihapus.',
        'izin' => ['@owner'],
        'route_names' => ['aktivitas-tim-it.*'],
        'terkait' => ['audit-log', 'tim-it'],
        'kata_kunci' => 'aktivitas tim it catatan teknisi siapa yang mengubah vendor login tim it jejak tim it keamanan',
        'konten' => <<<'MD'
## Fungsi
Menunjukkan kapan tim IT penyedia aplikasi masuk ke aplikasi Anda dan apa yang mereka lakukan (menu, waktu, alamat IP). Isi formulir (mis. kata sandi) tidak disimpan.

## Supaya menu ini muncul & bisa dipakai
- Khusus **Owner** (dan Tim IT). Tidak ada centangan untuk role lain.
- Catatan baru ada kalau tim IT pernah login atau melakukan perubahan.

## Apa yang terjadi kalau tombol diklik
| Tombol | Akibat |
|---|---|
| Tampilkan (dari–sampai) | Hanya menyaring tampilan. Tidak ada tombol hapus |

## Menu yang mirip & bedanya
- **Audit Log**: perubahan data oleh semua user (data lama → baru). Aktivitas Tim IT khusus akun tim IT, termasuk login.
MD,
    ],
];
