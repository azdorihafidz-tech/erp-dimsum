<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanAuditBuktiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user    = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        $dari      = $request->filled('dari')      ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai    = $request->filled('sampai')    ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
        $threshold = (int) ($request->threshold ?? 500000);
        $hanyaTanpaBukti = $request->boolean('tanpa_bukti', false);

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $base = fn() => TransaksiKeuangan::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tipe', 'pengeluaran')
            ->where('jumlah', '>', $threshold)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId));

        $totalAtas       = $base()->count();
        $sudahUpload     = $base()->whereNotNull('bukti_path')->count();
        $belumUpload     = $base()->whereNull('bukti_path')->count();
        $totalNominal    = $base()->sum('jumlah');
        $pctUpload       = $totalAtas > 0 ? round(($sudahUpload / $totalAtas) * 100, 1) : 0;

        $query = $base()
            ->with(['cabang:id,nama_cabang', 'kategoriDinamis:id,nama'])
            ->when($hanyaTanpaBukti, fn($q) => $q->whereNull('bukti_path'))
            ->orderByDesc('jumlah');

        if ($request->export === 'excel') {
            return $this->exportExcel($query->get(), $dari, $sampai, $threshold);
        }

        $transaksis = $query->paginate(25)->withQueryString();

        return view('laporan.audit-bukti', compact(
            'transaksis', 'cabangs', 'cabangId', 'dari', 'sampai', 'threshold', 'hanyaTanpaBukti',
            'totalAtas', 'sudahUpload', 'belumUpload', 'totalNominal', 'pctUpload'
        ));
    }

    private function exportExcel($rows, Carbon $dari, Carbon $sampai, int $threshold)
    {
        $headers = [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-bukti-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xls"',
        ];
        $callback = function () use ($rows, $threshold) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['LAPORAN AUDIT BUKTI — Pengeluaran > Rp ' . number_format($threshold, 0, ',', '.')], ';');
            fputcsv($file, [], ';');
            fputcsv($file, ['Tanggal', 'No. Transaksi', 'Keterangan', 'Kategori', 'Cabang', 'Jumlah', 'Status Bukti'], ';');
            foreach ($rows as $r) {
                fputcsv($file, [
                    optional($r->tanggal_transaksi)->format('d/m/Y'),
                    $r->nomor_transaksi,
                    $r->keterangan,
                    $r->kategoriDinamis?->nama ?? '-',
                    $r->cabang?->nama_cabang,
                    $r->jumlah,
                    $r->bukti_path ? 'Sudah Upload' : 'BELUM UPLOAD',
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
