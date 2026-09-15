<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\BepOtomatisService;
use App\Services\NeracaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Menu Interactive Simulator BEP — slider input real-time (volume, harga
 * jual, biaya variabel, beban tetap) untuk eksperimen Owner. Kalkulasi BEP
 * dijalankan di client-side JavaScript (aljabar dasar, bukan data/logic
 * otoritatif) — nilai AWAL slider REUSE dari BepOtomatisService/NeracaService
 * (bulan berjalan), tidak ada query/kalkulasi akuntansi baru di controller.
 */
class SimulatorBepController extends Controller
{
    public function __construct(
        private BepOtomatisService $bepOtomatisService,
        private NeracaService $neracaService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.simulator.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $tanggal = Carbon::now();
        $mulaiBulan = $tanggal->copy()->startOfMonth();

        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulaiBulan, $tanggal->copy()->endOfDay(), $cabangId);
        $neraca = $this->neracaService->hitungNeraca($tanggal, $cabangId);

        $defaultValues = [
            'volume_harian_kg' => round($bep['volume_aktual'] / max(1, $mulaiBulan->diffInDays($tanggal) + 1), 2),
            'harga_jual_per_kg' => round($bep['harga_jual_per_unit'], 2),
            'biaya_variabel_per_kg' => round($bep['biaya_variabel_per_unit'], 2),
            'biaya_tetap_bulanan' => round($bep['biaya_tetap'], 0),
            'modal_awal' => round($neraca['modal']['modal_owner'], 0),
        ];

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        return view('laporan.simulator-bep.index', compact('cabangs', 'cabangId', 'cabangNama', 'defaultValues'));
    }
}
