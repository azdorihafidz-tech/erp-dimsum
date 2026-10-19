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

    /** Cabang yang boleh dipilih sbg pemilik barang baru saat mode "Semua Cabang". */
    public static function pilihan()
    {
        return OutletTerpilih::pilihan(auth()->user());
    }
}
