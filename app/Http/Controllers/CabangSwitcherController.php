<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use Illuminate\Http\Request;

class CabangSwitcherController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'cabang_id' => 'nullable|exists:cabangs,id',
        ]);

        $user = $request->user();
        $cabangId = $request->input('cabang_id');

        // Validasi akses
        if ($cabangId && !$user->canAccessAllBranches()) {
            $userCabangIds = $user->cabangs()->pluck('cabangs.id')->toArray();
            if (!in_array($cabangId, $userCabangIds)) {
                abort(403, 'Anda tidak memiliki akses ke cabang ini.');
            }
        }

        // null = lihat semua (hanya untuk owner/admin_pusat)
        if ($cabangId === null && !$user->canAccessAllBranches()) {
            abort(403);
        }

        session(['active_cabang_id' => $cabangId]);

        return back()->with('success', 'Cabang aktif berhasil diubah.');
    }
}
