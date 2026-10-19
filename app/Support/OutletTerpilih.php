<?php

namespace App\Support;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Outlet yang sedang dilihat di halaman Bahan Baku / Produk Jual (CLAUDE.md
 * 4.38-F). Urutan: ?cabang_id= (kalau user boleh akses) → Cabang Aktif di
 * session → null (= "Semua Cabang", tampil harga umum).
 */
class OutletTerpilih
{
    /** Outlet yang boleh dipilih user: semua cabang aktif atau cabang miliknya saja. */
    public static function pilihan(User $user): Collection
    {
        return $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : $user->cabangs()->aktif()->orderBy('nama_cabang')->get();
    }

    /**
     * Untuk halaman daftar: ?cabang_id=0 = Harga Umum (hanya role akses semua
     * cabang). Role cabang selalu melihat outletnya sendiri.
     */
    public static function untukDaftar(Request $request): ?Cabang
    {
        if ($request->input('cabang_id') === '0' && $request->user()->canAccessAllBranches()) {
            return null;
        }

        return self::resolve($request, ! $request->user()->canAccessAllBranches());
    }

    public static function resolve(Request $request, bool $wajib = false): ?Cabang
    {
        $pilihan = self::pilihan($request->user());

        $id = $request->filled('cabang_id') ? (int) $request->input('cabang_id') : (int) session('active_cabang_id');
        $cabang = $pilihan->firstWhere('id', $id);

        if (! $cabang && $request->filled('cabang_id')) {
            abort(403, 'Anda tidak punya akses ke outlet tersebut.');
        }

        return $cabang ?? ($wajib ? $pilihan->first() : null);
    }
}
