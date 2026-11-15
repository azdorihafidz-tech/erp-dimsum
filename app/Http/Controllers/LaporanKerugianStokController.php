<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKerugianStokExport;
use App\Models\Cabang;
use App\Services\LaporanKerugianStokService;
use App\Support\CabangAktif;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** Laporan Kerugian Stok (Stok Opname). Filter lokasi manual (CLAUDE.md 3.4). */
class LaporanKerugianStokController extends Controller
{
    public function __construct(private LaporanKerugianStokService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('stok_opname.laporan'), 403);

        $lokasi = $user->canAccessAllBranches()
            ? Cabang::where('is_active', true)->orderBy('nama_cabang')->get()
            : $user->cabangs()->orderBy('nama_cabang')->get();
        $aktif = CabangAktif::id();
        $filter = $request->filled('cabang_id') ? $request->integer('cabang_id') : ($aktif && $lokasi->contains('id', $aktif) ? $aktif : null);
        $ids = $filter && $lokasi->contains('id', $filter) ? [$filter] : $lokasi->pluck('id')->all();

        $dari = $request->filled('dari') ? Carbon::parse($request->input('dari'))->startOfDay() : today()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->input('sampai'))->startOfDay() : today();
        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        $data = $this->service->hitung($dari, $sampai, $ids);
        $namaLokasi = count($ids) === 1 ? ($lokasi->firstWhere('id', $ids[0])?->nama_cabang ?? '-') : 'Semua lokasi';

        if ($request->input('format') === 'excel') {
            return Excel::download(new LaporanKerugianStokExport($data, $namaLokasi, $user->name),
                'Laporan-Kerugian-Stok-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx');
        }
        if ($request->input('format') === 'pdf') {
            return Pdf::loadView('laporan.kerugian-stok.pdf', [
                'data'          => $data,
                'judulLaporan'  => 'Laporan Kerugian Stok',
                'filterInfo'    => ['Periode' => $dari->format('d/m/Y') . ' – ' . $sampai->format('d/m/Y'), 'Lokasi' => $namaLokasi],
                'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->format('d/m/Y H:i') . ' WIB',
            ])->setPaper('a4', 'portrait')->download('Laporan-Kerugian-Stok-' . $dari->format('Ymd') . '.pdf');
        }

        return view('laporan.kerugian-stok.index', compact('data', 'lokasi', 'filter', 'dari', 'sampai', 'namaLokasi'));
    }
}
