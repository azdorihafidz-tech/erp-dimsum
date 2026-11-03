<?php

namespace App\Support;

use App\Models\PengaturanUmum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas usaha (CLAUDE.md 4.44 Poin 3, porting dari erp-coffeeshop aa0ca0c):
 * nama, nama singkat, tagline, telepon & logo dari Pengaturan Umum. Kode yang
 * sama dipasang di beberapa database (D'mentai live + demo), jadi JANGAN menulis
 * nama/logo usaha langsung di view — pakai `$brand` (dibagikan ke semua view).
 *
 * Logo:
 * - upload BARU → public/uploads/branding/logo-*.png (diabaikan git) + ikon
 *   persegi 8 ukuran dibuat otomatis (kalau GD tersedia);
 * - upload LAMA (sebelum fitur ini) → disk `public` (logo/...), dilayani
 *   route /img/{path} yang aman utk hosting tanpa symlink (4.18);
 * - belum ada → logo bawaan public/images/logo.png & ikon bawaan.
 */
class Branding
{
    public const CACHE_KEY = 'branding:v1';
    public const FOLDER = 'uploads/branding';
    public const UKURAN_IKON = [72, 96, 128, 144, 152, 192, 384, 512];

    private const LOGO_BAWAAN = 'images/logo.png';
    private const FAVICON_BAWAAN = 'images/logo-icon.png';

    private ?array $data = null;

    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        try {
            $this->data = Cache::rememberForever(self::CACHE_KEY, function () {
                $s = PengaturanUmum::query()->first();

                return $s ? [
                    'nama'         => $s->nama_perusahaan,
                    'nama_singkat' => $s->nama_singkat,
                    'tagline'      => $s->tagline,
                    'alamat'       => $s->alamat_perusahaan,
                    'telepon'      => $s->telepon,
                    'logo_path'    => $s->logo_path,
                    'versi'        => $s->updated_at?->timestamp,
                ] : [];
            });
        } catch (\Throwable) {
            // Database belum siap (instalasi baru / halaman error) — pakai bawaan
            $this->data = [];
        }

        return $this->data;
    }

    /** Hapus cache setelah Pengaturan Umum disimpan. */
    public function lupakan(): void
    {
        $this->data = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    public function nama(): string
    {
        return trim((string) ($this->data()['nama'] ?? '')) ?: (config('app.name') ?: 'ERP');
    }

    /** Untuk tempat sempit: sidebar, judul tab, struk, laporan, aplikasi kasir. */
    public function namaSingkat(): string
    {
        return trim((string) ($this->data()['nama_singkat'] ?? '')) ?: $this->nama();
    }

    public function tagline(): ?string
    {
        return trim((string) ($this->data()['tagline'] ?? '')) ?: null;
    }

    public function alamat(): ?string
    {
        return trim((string) ($this->data()['alamat'] ?? '')) ?: null;
    }

    public function telepon(): ?string
    {
        return trim((string) ($this->data()['telepon'] ?? '')) ?: null;
    }

    /** Baris kecil di bawah nama (header PDF): tagline · telepon. */
    public function subjudul(): string
    {
        return collect([$this->tagline(), $this->telepon()])->filter()->implode(' · ');
    }

    /** Baris footer struk kalau cabang belum mengisi Footer Struk sendiri. */
    public function footerStrukBaris(): array
    {
        return ['Terima kasih atas kunjungan Anda!', $this->namaSingkat() . ($this->tagline() ? ' - ' . $this->tagline() : '')];
    }

    // ------------------------------------------------------------------ logo

    public function adaLogoUpload(): bool
    {
        return $this->lokasiLogo()['jenis'] !== 'bawaan';
    }

    /** URL logo untuk <img> di halaman web. */
    public function logoUrl(): string
    {
        $l = $this->lokasiLogo();

        return match ($l['jenis']) {
            'upload' => asset($l['path']) . $this->versiQuery(),
            'lama'   => url('/img/' . $l['path']) . $this->versiQuery(),
            default  => asset(self::LOGO_BAWAAN),
        };
    }

    /** Path file logo di disk — untuk PDF (dompdf). null kalau tidak ada. */
    public function logoFile(): ?string
    {
        $l = $this->lokasiLogo();

        return $l['file'] && is_file($l['file']) ? $l['file'] : null;
    }

    /** Ikon persegi hasil upload logo baru (null kalau belum ada). */
    private function ikonUpload(int $ukuran): ?string
    {
        $rel = self::FOLDER . "/icon-{$ukuran}.png";

        return $this->lokasiLogo()['jenis'] === 'upload' && is_file(public_path($rel))
            ? asset($rel) . $this->versiQuery()
            : null;
    }

    /** Favicon tab browser — bawaan: maskot images/logo-icon.png (seperti sebelumnya). */
    public function faviconUrl(): string
    {
        return $this->ikonUpload(96) ?? asset(self::FAVICON_BAWAAN);
    }

    /** Ikon persegi (apple-touch-icon, PWA, aplikasi kasir). */
    public function ikonUrl(int $ukuran = 192): string
    {
        if ($u = $this->ikonUpload($ukuran)) {
            return $u;
        }

        return is_file(public_path("images/icons/icon-{$ukuran}x{$ukuran}.png"))
            ? asset("images/icons/icon-{$ukuran}x{$ukuran}.png")
            : asset(self::FAVICON_BAWAAN);
    }

    /**
     * Nama & ikon ke manifest PWA (laravelpwa membaca config statis). Ikon
     * hanya diganti kalau ada ikon hasil upload baru — selain itu config bawaan.
     */
    public function terapkanKeConfigPwa(): void
    {
        $config = [
            'laravelpwa.name'                => 'ERP ' . $this->namaSingkat(),
            'laravelpwa.manifest.name'       => 'ERP ' . $this->namaSingkat(),
            'laravelpwa.manifest.short_name' => $this->namaSingkat(),
        ];

        if ($this->ikonUpload(192)) {
            $ikon = [];
            foreach (self::UKURAN_IKON as $u) {
                $ikon["{$u}x{$u}"] = [
                    'path'    => '/' . self::FOLDER . "/icon-{$u}.png",
                    'purpose' => in_array($u, [192, 512], true) ? 'any maskable' : 'any',
                ];
            }
            $config['laravelpwa.manifest.icons'] = $ikon;
        }

        config($config);
    }

    /** Data untuk aplikasi kasir Android (endpoint /app-info). */
    public function untukAplikasi(): array
    {
        return [
            'nama'         => $this->nama(),
            'nama_singkat' => $this->namaSingkat(),
            'tagline'      => $this->tagline(),
            'logo_url'     => $this->logoUrl(),
            'ikon_url'     => $this->ikonUrl(192),
        ];
    }

    /**
     * Simpan logo upload sebagai PNG (diperkecil maks 1024 px) + buat ikon
     * persegi semua ukuran. Return path relatif public/ untuk logo_path.
     * Tanpa ekstensi GD → simpan file apa adanya (tanpa ikon), tidak gagal.
     */
    public function simpanLogo(UploadedFile $file): string
    {
        $folder = public_path(self::FOLDER);
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $stempel = now()->format('YmdHis');

        if (! function_exists('imagecreatefromstring')) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $nama = "logo-{$stempel}.{$ext}";
            $file->move($folder, $nama);
            $this->hapusLogoLama($nama);

            return self::FOLDER . '/' . $nama;
        }

        $nama = "logo-{$stempel}.png";
        $tujuan = $folder . DIRECTORY_SEPARATOR . $nama;

        $batasLama = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            if ($src === false) {
                throw new \RuntimeException('File logo tidak bisa dibaca sebagai gambar.');
            }
            $logo = $this->skala($src, 1024);
            imagepng($logo, $tujuan, 6);

            foreach (self::UKURAN_IKON as $u) {
                $ikon = $this->kotak($logo, $u);
                imagepng($ikon, $folder . DIRECTORY_SEPARATOR . "icon-{$u}.png", 6);
                imagedestroy($ikon);
            }
            imagedestroy($logo);
        } finally {
            ini_set('memory_limit', $batasLama);
        }

        $this->hapusLogoLama($nama);

        return self::FOLDER . '/' . $nama;
    }

    /** Hapus semua file logo & ikon upload baru (kembali ke logo bawaan). */
    public function hapusFileLogo(): void
    {
        $this->hapusLogoLama(null);
        foreach (self::UKURAN_IKON as $u) {
            @unlink(public_path(self::FOLDER . "/icon-{$u}.png"));
        }
    }

    // ----------------------------------------------------------- internal

    /** @return array{jenis: string, path: ?string, file: ?string} */
    private function lokasiLogo(): array
    {
        $p = ltrim((string) ($this->data()['logo_path'] ?? ''), '/');

        if ($p !== '' && ! str_contains($p, '..')) {
            if (str_starts_with($p, self::FOLDER . '/') && is_file(public_path($p))) {
                return ['jenis' => 'upload', 'path' => $p, 'file' => public_path($p)];
            }
            try {
                if (! str_starts_with($p, self::FOLDER . '/') && Storage::disk('public')->exists($p)) {
                    return ['jenis' => 'lama', 'path' => $p, 'file' => Storage::disk('public')->path($p)];
                }
            } catch (\Throwable) {
            }
        }

        return ['jenis' => 'bawaan', 'path' => self::LOGO_BAWAAN, 'file' => public_path(self::LOGO_BAWAAN)];
    }

    private function versiQuery(): string
    {
        $v = $this->data()['versi'] ?? null;

        return $v ? '?v=' . $v : '';
    }

    private function hapusLogoLama(?string $kecuali): void
    {
        foreach (glob(public_path(self::FOLDER . '/logo-*')) ?: [] as $f) {
            if ($kecuali === null || basename($f) !== $kecuali) {
                @unlink($f);
            }
        }
    }

    /** Perkecil (tidak memperbesar) dengan menjaga transparansi. */
    private function skala(\GdImage $src, int $maks): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $r = min(1, $maks / max($w, $h));
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));

        $dst = $this->kanvas($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    /** Logo di tengah kanvas persegi transparan (jarak tepi 8%). */
    private function kotak(\GdImage $logo, int $u): \GdImage
    {
        $w = imagesx($logo);
        $h = imagesy($logo);
        $r = ($u * 0.84) / max($w, $h);
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));

        $dst = $this->kanvas($u, $u);
        imagecopyresampled($dst, $logo, (int) (($u - $nw) / 2), (int) (($u - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function kanvas(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

        return $img;
    }
}
