<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\LaporanPerlengkapanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Controller BARU.
 */
class LaporanPerlengkapanController extends Controller
{
    public function __construct(private LaporanPerlengkapanService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.perlengkapan.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.perlengkapan.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.perlengkapan.export'), 403);

        $data = $this->buildData($request);

        $filename = 'laporan-pemakaian-perlengkapan-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Item', 'Cabang', 'Qty', 'Satuan', 'Nilai', 'Keterangan']);
            foreach ($data['detail'] as $row) {
                fputcsv($out, [
                    Carbon::parse($row->tanggal_pemakaian)->format('d/m/Y'),
                    $row->nama_item,
                    $row->nama_cabang,
                    $row->qty,
                    $row->satuan,
                    $row->nilai,
                    $row->keterangan,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function buildData(Request $request): array
    {
        $mulai = $request->filled('dari') ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $akhir = $request->filled('sampai') ? Carbon::parse($request->sampai) : Carbon::now()->endOfMonth();
        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;

        $user = auth()->user();
        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return [
            'mulai'     => $mulai,
            'akhir'     => $akhir,
            'cabangId'  => $cabangId,
            'cabangs'   => $cabangs,
            'ringkasan' => $this->service->getRingkasan($mulai, $akhir, $cabangId),
            'breakdown' => $this->service->getBreakdownPerItem($mulai, $akhir, $cabangId),
            'detail'    => $this->service->getDetailPemakaian($mulai, $akhir, $cabangId),
            'trend'     => $this->service->getTrendBulanan($cabangId),
        ];
    }
}
