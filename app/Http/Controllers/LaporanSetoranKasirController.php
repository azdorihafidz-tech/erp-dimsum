<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Setoran;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Tahap 6 D'mentai — Laporan Setoran Kasir (BEDA dari LaporanSetoranController
 * existing yang melaporkan Transfer Dana/SETOR-OUT generik).
 */
class LaporanSetoranKasirController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_kasir.view'), 403);

        $user = auth()->user();
        $dari = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now();

        $query = Setoran::with(['cabang', 'disubmitOleh', 'disetujuiOleh'])
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $stats = [
            'total_sistem' => (clone $query)->sum('total_penjualan_sistem'),
            'total_disetor' => (clone $query)->sum('total_disetor'),
            'total_selisih' => (clone $query)->sum('selisih'),
            'jumlah' => (clone $query)->count(),
        ];

        $setorans = $query->orderByDesc('tanggal')->paginate(20)->withQueryString();
        $cabangOptions = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('laporan.setoran-kasir', compact('setorans', 'stats', 'cabangOptions', 'dari', 'sampai'));
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_kasir.export'), 403);

        $user = auth()->user();
        $dari = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now();

        $query = Setoran::with('cabang')->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        $cabangId = $request->cabang_id ?: (!$user->canAccessAllBranches() ? (session('active_cabang_id') ?? $user->defaultCabangId()) : null);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->orderByDesc('tanggal')->get();

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-setoran-kasir-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xls"',
        ];
        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'Cabang', 'Total Sistem', 'Total Disetor', 'Selisih', 'Status'], ';');
            foreach ($rows as $r) {
                fputcsv($file, [
                    $r->tanggal->format('d/m/Y'), $r->cabang?->nama_cabang,
                    $r->total_penjualan_sistem, $r->total_disetor, $r->selisih, $r->status->value,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
