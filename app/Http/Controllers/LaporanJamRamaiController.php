<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\JamRamaiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Laporan Analisa Jam Ramai (Peak Hours) — menu BARU. Murni compose dari
 * JamRamaiService (Orders-only, lihat catatan class-level di service itu
 * untuk alasan transaksi_keuangans TIDAK dipakai). Read-only.
 */
class LaporanJamRamaiController extends Controller
{
    public function __construct(private JamRamaiService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.jam_ramai.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $mulai = $request->filled('mulai') ? Carbon::parse($request->mulai) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('akhir') ? Carbon::parse($request->akhir)->endOfDay() : Carbon::now()->endOfDay();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        $analisa = $this->service->getAnalisaJamRamai($mulai, $akhir, $cabangId);

        return view('laporan.jam-ramai.index', compact('cabangs', 'cabangId', 'cabangNama', 'mulai', 'akhir', 'analisa'));
    }
}
