<?php

namespace App\Observers;

use App\Models\PengaturanUmum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Tahap 7 D'mentai (2026-09-17) — sinkron sebagian CLAUDE.md 4.2 (2 mekanisme
 * logo terpisah). Setiap kali logo diupload lewat Pengaturan Umum, otomatis
 * copy ke `public/images/logo.png` (file statis yang dibaca sidebar/login/
 * PDF header) — supaya Owner tidak perlu upload manual 2x.
 *
 * SENGAJA CUMA sinkron logo UTAMA (bukan `logo-icon.png`/8 ikon PWA) — itu
 * butuh crop/resize mascot manual, keterbatasan yang sudah terdokumentasi di
 * CLAUDE.md 4.3, tidak diotomatisasi di sini.
 *
 * Kalau logo DIHAPUS (hapus_logo checkbox), `public/images/logo.png` SENGAJA
 * TIDAK ikut dihapus — biarkan app tetap punya fallback logo statis daripada
 * tampil kosong sama sekali.
 */
class PengaturanUmumObserver
{
    public function saved(PengaturanUmum $setting): void
    {
        if (! $setting->wasChanged('logo_path') || ! $setting->logo_path) {
            return;
        }

        if (! Storage::disk('public')->exists($setting->logo_path)) {
            return;
        }

        try {
            $isi = Storage::disk('public')->get($setting->logo_path);
            file_put_contents(public_path('images/logo.png'), $isi);
        } catch (\Throwable $e) {
            // Non-fatal — logo dinamis tetap tersimpan benar di DB/Storage,
            // cuma sinkronisasi ke file statis yang gagal (mis. permission
            // folder public/images/). Jangan sampai gagal save Pengaturan Umum.
            Log::warning('Gagal sinkron logo ke public/images/logo.png: ' . $e->getMessage());
        }
    }
}
