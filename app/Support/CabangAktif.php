<?php

namespace App\Support;

use App\Models\Cabang;

/**
 * Cabang yang dipilih di switcher header (pojok kanan atas) — menentukan data
 * barang yang tampil di semua menu (CLAUDE.md 4.39). null = "Semua Cabang"
 * (hanya role akses semua cabang). Role cabang selalu cabangnya sendiri.
 */
class CabangAktif
{
    public static function id(): ?int
    {
        $user = auth()->user();
        $id = session('active_cabang_id');

        if (! $user) {
            return $id ? (int) $id : null;
        }
        if (! $id && ! $user->canAccessAllBranches()) {
            $id = $user->defaultCabangId() ?? $user->cabangs()->value('cabangs.id');
        }

        return $id ? (int) $id : null;
    }

    public static function model(): ?Cabang
    {
        $id = self::id();

        return $id ? Cabang::find($id) : null;
    }

    /** User boleh mengelola data milik cabang ini? (NULL = data lama, boleh). */
    public static function bolehAkses(?int $cabangId): bool
    {
        $user = auth()->user();
        if (! $cabangId || ! $user || $user->canAccessAllBranches()) {
            return true;
        }

        return $user->cabangs()->where('cabangs.id', $cabangId)->exists();
    }

    /**
     * Pemilik untuk data baru: cabang aktif; di mode "Semua Cabang" diambil
     * dari input cabang_id (harus cabang yang boleh diakses), default Gudang Pusat.
     */
    public static function pemilikBaru(?int $inputCabangId): ?int
    {
        $id = self::id() ?? $inputCabangId;
        // Semua Cabang tanpa pilihan → Gudang Pusat (pemilik default)
        if (! $id && auth()->user()?->canAccessAllBranches()) {
            $id = app(\App\Services\PemecahanDataCabangService::class)->cabangUtamaDefault();
        }

        return $id && self::pilihan()->contains('id', $id) ? (int) $id : null;
    }

    /** Cabang yang boleh dipilih sbg pemilik barang baru saat mode "Semua Cabang". */
    public static function pilihan()
    {
        $user = auth()->user();

        return $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : $user->cabangs()->aktif()->orderBy('nama_cabang')->get();
    }
}
