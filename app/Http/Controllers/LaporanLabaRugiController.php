<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\LaporanLabaRugiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Laporan Laba Rugi — analisis gross profit (omzet vs HPP bahan) per item,
 * kategori, jenis olahan, atau order. BUKAN cash flow (itu Laporan
 * Keuangan) — murni profit produksi dari order_items. Murni READ.
 */
class LaporanLabaRugiController extends Controller
{
    public function __construct(private LaporanLabaRugiService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.laba-rugi.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.laba-rugi.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.laba_rugi.export'), 403);

        $data = $this->buildData($request);

        return $this->exportCsv($data);
    }

    /**
     * Resolve filter (dari/sampai/cabang/level/sort) + ambil semua data
     * dari service. Default periode: BULAN INI, level: Per Item.
     */
    private function buildData(Request $request): array
    {
        $user = auth()->user();

        $dari = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::now()->endOfDay();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $level = in_array($request->get('level'), ['item', 'kategori', 'jenis_olahan', 'order'])
            ? $request->get('level')
            : 'item';
        $sort = $request->get('sort');
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';

        $ringkasan = $this->service->getRingkasan($dari, $sampai, $cabangId);

        $breakdownPenuh = match ($level) {
            'kategori'     => $this->service->getBreakdownPerKategori($dari, $sampai, $cabangId, $sort, $dir),
            'jenis_olahan' => $this->service->getBreakdownPerJenisOlahan($dari, $sampai, $cabangId, $sort, $dir),
            'order'        => $this->service->getBreakdownPerOrder($dari, $sampai, $cabangId, $sort, $dir),
            default        => $this->service->getBreakdownPerItem($dari, $sampai, $cabangId, $sort, $dir),
        };

        $breakdown = $this->paginate($breakdownPenuh, $request);

        $trend     = $this->service->getTrendUntungBulanan($cabangId, $sampai);
        $topUntung = $this->service->getTopUntung($dari, $sampai, $cabangId, 10);
        $perKategoriPie = $level === 'kategori'
            ? $breakdownPenuh
            : $this->service->getBreakdownPerKategori($dari, $sampai, $cabangId);
        $detail = $this->service->getDetailTransaksi($dari, $sampai, $cabangId);

        $cabangs = Cabang::aktif()->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdown',
            'breakdownPenuh',
            'trend',
            'topUntung',
            'perKategoriPie',
            'detail',
            'cabangs',
            'cabangId',
            'cabangTerpilih',
            'dari',
            'sampai',
            'level',
            'sort',
            'dir'
        );
    }

    /** Paginate manual (50/halaman) — dataset breakdown sudah Collection hasil agregasi. */
    private function paginate($rows, Request $request)
    {
        $perPage = 50;
        $page    = max(1, (int) $request->get('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->except('page'),
            ]
        );
    }

    private function exportCsv(array $data)
    {
        $dari   = $data['dari'];
        $sampai = $data['sampai'];
        $level  = $data['level'];
        $filename = 'laba-rugi-' . $level . '-' . $dari->toDateString() . '_' . $sampai->toDateString() . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $labelLevel = [
            'item' => 'Item', 'kategori' => 'Kategori', 'jenis_olahan' => 'Jenis Menu', 'order' => 'Order',
        ][$level] ?? 'Item';

        $callback = function () use ($data, $dari, $sampai, $level, $labelLevel) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, ['LAPORAN LABA RUGI'], ';');
            fputcsv($file, ['Periode', $dari->format('d/m/Y') . ' - ' . $sampai->format('d/m/Y')], ';');
            fputcsv($file, ['Cabang', $data['cabangTerpilih']->nama_cabang ?? 'Semua Cabang'], ';');
            fputcsv($file, ['Level Breakdown', $labelLevel], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['RINGKASAN'], ';');
            fputcsv($file, ['Total Order', $data['ringkasan']['total_order']], ';');
            fputcsv($file, ['Total Omzet', $data['ringkasan']['total_omzet']], ';');
            fputcsv($file, ['Total HPP', $data['ringkasan']['total_hpp']], ';');
            fputcsv($file, ['Total Untung', $data['ringkasan']['total_untung']], ';');
            fputcsv($file, ['Margin', $data['ringkasan']['margin'] !== null ? $data['ringkasan']['margin'] . '%' : '-'], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['BREAKDOWN PER ' . strtoupper($labelLevel)], ';');
            if ($level === 'order') {
                fputcsv($file, ['No Order', 'Tanggal', 'Pelanggan', 'Kasir', 'Omzet', 'HPP', 'Untung', 'Margin'], ';');
                foreach ($data['breakdownPenuh'] as $b) {
                    fputcsv($file, [
                        $b->nomor_order, \Carbon\Carbon::parse($b->tanggal_order)->format('d/m/Y'),
                        $b->nama_pelanggan ?? 'Umum', $b->kasir_nama ?? '-',
                        $b->total_omzet, $b->total_hpp, $b->total_untung,
                        $b->margin !== null ? $b->margin . '%' : '-',
                    ], ';');
                }
            } elseif ($level === 'kategori') {
                fputcsv($file, ['Kategori', 'Omzet', 'HPP', 'Untung', 'Margin'], ';');
                foreach ($data['breakdownPenuh'] as $b) {
                    fputcsv($file, [ucfirst($b->kategori), $b->total_omzet, $b->total_hpp, $b->total_untung, $b->margin !== null ? $b->margin . '%' : '-'], ';');
                }
            } elseif ($level === 'jenis_olahan') {
                fputcsv($file, ['Jenis Menu', 'Omzet', 'HPP', 'Untung', 'Margin'], ';');
                foreach ($data['breakdownPenuh'] as $b) {
                    fputcsv($file, [ucfirst($b->jenis_olahan), $b->total_omzet, $b->total_hpp, $b->total_untung, $b->margin !== null ? $b->margin . '%' : '-'], ';');
                }
            } else {
                fputcsv($file, ['Item', 'Tipe', 'Qty', 'Satuan', 'Omzet', 'HPP', 'Untung', 'Margin'], ';');
                foreach ($data['breakdownPenuh'] as $b) {
                    fputcsv($file, [
                        $b->nama_item, $b->tipe, $b->total_qty, $b->satuan,
                        $b->total_omzet, $b->total_hpp, $b->total_untung,
                        $b->margin !== null ? $b->margin . '%' : '-',
                    ], ';');
                }
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['DETAIL TRANSAKSI'], ';');
            fputcsv($file, ['Tanggal', 'Waktu', 'No Order', 'Kategori', 'Item', 'Qty', 'Satuan', 'Omzet', 'HPP', 'Untung', 'Kasir'], ';');
            foreach ($data['detail'] as $tanggal => $rows) {
                foreach ($rows as $r) {
                    fputcsv($file, [
                        $tanggal,
                        \Carbon\Carbon::parse($r->order_created_at)->format('H:i'),
                        $r->nomor_order,
                        ucfirst($r->kategori),
                        $r->nama_item,
                        $r->qty,
                        $r->satuan,
                        $r->omzet,
                        $r->hpp,
                        $r->untung,
                        $r->kasir_nama ?? '-',
                    ], ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
