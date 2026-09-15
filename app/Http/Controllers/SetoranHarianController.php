<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\SetoranHarianService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Laporan Setoran Harian — konsolidasi pemasukan/pengeluaran/net 1 halaman
 * untuk kebutuhan setoran ke pusat (menggantikan cek manual 3 halaman
 * terpisah). Murni READ, tidak mengubah data apapun.
 */
class SetoranHarianController extends Controller
{
    public function __construct(private SetoranHarianService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.view'), 403);

        $data = $this->buildData($request);

        return view('laporan.setoran-harian.index', $data);
    }

    public function print(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.print'), 403);

        $data = $this->buildData($request);

        return view('laporan.setoran-harian.print', $data);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.setoran_harian.export'), 403);

        $data = $this->buildData($request);

        return $this->exportCsv($data);
    }

    /**
     * Resolve filter (dari/sampai/cabang) + ambil semua data dari service.
     * Default periode: HARI INI (beda dari kebanyakan laporan lain yang
     * default bulan ini — setoran harian butuh kecepatan cek hari berjalan).
     */
    private function buildData(Request $request): array
    {
        $user = auth()->user();

        $dari = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::today();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::today()->endOfDay();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }
        $cabangId = $cabangId ? (int) $cabangId : null;

        $ringkasan          = $this->service->getRingkasan($dari, $sampai, $cabangId);
        $breakdownPemasukan = $this->service->getBreakdownPemasukan($dari, $sampai, $cabangId);
        $breakdownPengeluaran = $this->service->getBreakdownPengeluaran($dari, $sampai, $cabangId);
        $detailOrder        = $this->service->getDetailOrder($dari, $sampai, $cabangId);
        $detailPengeluaran  = $this->service->getDetailPengeluaran($dari, $sampai, $cabangId);

        $cabangs = Cabang::aktif()->get();
        $cabangTerpilih = $cabangId ? $cabangs->firstWhere('id', $cabangId) : null;

        return compact(
            'ringkasan',
            'breakdownPemasukan',
            'breakdownPengeluaran',
            'detailOrder',
            'detailPengeluaran',
            'cabangs',
            'cabangId',
            'cabangTerpilih',
            'dari',
            'sampai'
        );
    }

    private function exportCsv(array $data)
    {
        $dari   = $data['dari'];
        $sampai = $data['sampai'];
        $filename = 'setoran-harian-' . $dari->toDateString() . '_' . $sampai->toDateString() . '.xls';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data, $dari, $sampai) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, ['LAPORAN SETORAN HARIAN'], ';');
            fputcsv($file, ['Periode', $dari->format('d/m/Y') . ' - ' . $sampai->format('d/m/Y')], ';');
            if ($data['cabangTerpilih']) {
                fputcsv($file, ['Cabang', $data['cabangTerpilih']->nama_cabang], ';');
            } else {
                fputcsv($file, ['Cabang', 'Semua Cabang'], ';');
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['RINGKASAN'], ';');
            fputcsv($file, ['Total Order', $data['ringkasan']['total_order']], ';');
            fputcsv($file, ['Total Pemasukan', $data['ringkasan']['pemasukan']], ';');
            fputcsv($file, ['Total Pengeluaran', $data['ringkasan']['pengeluaran']], ';');
            fputcsv($file, ['Setoran ke Pusat (Net)', $data['ringkasan']['net']], ';');
            fputcsv($file, [], ';');

            fputcsv($file, ['BREAKDOWN PEMASUKAN PER METODE BAYAR (DARI ORDER)'], ';');
            fputcsv($file, ['Metode Bayar', 'Jumlah'], ';');
            foreach ($data['breakdownPemasukan'] as $b) {
                fputcsv($file, [$b['label'], $b['total']], ';');
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['BREAKDOWN PENGELUARAN PER KATEGORI'], ';');
            fputcsv($file, ['Kategori', 'Jumlah'], ';');
            foreach ($data['breakdownPengeluaran'] as $b) {
                fputcsv($file, [$b['label'], $b['total']], ';');
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['DETAIL ORDER'], ';');
            fputcsv($file, ['Tanggal', 'Waktu', 'No. Order', 'Pelanggan', 'Kasir', 'Tipe Bayar', 'Total'], ';');
            foreach ($data['detailOrder'] as $tanggal => $orders) {
                foreach ($orders as $o) {
                    fputcsv($file, [
                        $tanggal,
                        $o->created_at?->format('H:i'),
                        $o->nomor_order,
                        $o->nama_pelanggan ?? 'Umum',
                        $o->kasir?->name ?? '-',
                        $o->tipe_pembayaran?->label(),
                        $o->total_bayar,
                    ], ';');
                }
            }
            fputcsv($file, [], ';');

            fputcsv($file, ['DETAIL PENGELUARAN'], ';');
            fputcsv($file, ['Tanggal', 'Waktu', 'Kategori', 'Keterangan', 'Jumlah'], ';');
            foreach ($data['detailPengeluaran'] as $tanggal => $transaksis) {
                foreach ($transaksis as $t) {
                    fputcsv($file, [
                        $tanggal,
                        $t->created_at?->format('H:i'),
                        $t->label_kategori_pengeluaran,
                        $t->keterangan,
                        $t->jumlah,
                    ], ';');
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
