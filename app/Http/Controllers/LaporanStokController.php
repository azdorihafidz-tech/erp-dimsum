<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanStokController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Stock::with(['item', 'item.category', 'lokasi']);
        if ($lokasiId) {
            $query->where('lokasi_id', $lokasiId);
        }
        if ($request->kategori_id) {
            $query->whereHas('item', fn($q) => $q->where('item_category_id', $request->kategori_id));
        }

        // Export
        if ($request->export === 'excel') {
            $stocks = $query->get();
            return $this->exportExcel($stocks);
        }

        $stocks = $query->paginate(30)->withQueryString();

        // Statistik
        $totalItem = Stock::when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))->count();
        $stokRendah = Stock::whereColumn('qty', '<=', 'qty_minimum')
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();
        $stokKosong = Stock::where('qty', '<=', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        return view('laporan.stok.index', compact(
            'stocks', 'cabangs', 'lokasiId', 'totalItem', 'stokRendah', 'stokKosong'
        ));
    }

    public function pergerakan(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = StockMovement::with(['item', 'lokasiAsal', 'lokasiTujuan', 'user'])
            ->whereBetween('created_at', [$dari->startOfDay(), $sampai->endOfDay()]);

        if ($lokasiId) {
            $query->where(function ($q) use ($lokasiId) {
                $q->where('lokasi_asal_id', $lokasiId)
                  ->orWhere('lokasi_tujuan_id', $lokasiId);
            });
        }

        if ($request->tipe) {
            $query->where('tipe', $request->tipe);
        }

        $totalMasuk = (clone $query)->where('tipe', 'masuk')->sum('qty');
        $totalKeluar = (clone $query)->where('tipe', 'keluar')->sum('qty');
        $totalTransfer = (clone $query)->where('tipe', 'transfer')->count();

        if ($request->export === 'excel') {
            $movements = $query->orderByDesc('created_at')->get();
            return $this->exportPergerakanExcel($movements);
        }

        $movements = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('laporan.stok.pergerakan', compact(
            'movements', 'cabangs', 'lokasiId', 'dari', 'sampai',
            'totalMasuk', 'totalKeluar', 'totalTransfer'
        ));
    }

    public function minimum(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $stocks = Stock::with(['item', 'item.category', 'lokasi'])
            ->whereColumn('qty', '<=', 'qty_minimum')
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->orderByRaw('qty / NULLIF(qty_minimum, 0) ASC')
            ->paginate(30)
            ->withQueryString();

        $totalStokRendah = Stock::whereColumn('qty', '<=', 'qty_minimum')
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        $totalKosong = Stock::where('qty', '<=', 0)
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->count();

        return view('laporan.stok.minimum', compact(
            'stocks', 'cabangs', 'lokasiId', 'totalStokRendah', 'totalKosong'
        ));
    }

    private function exportExcel($stocks)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-stok-' . now()->format('Y-m-d') . '.xls"',
        ];
        $callback = function () use ($stocks) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Nama Barang', 'Kategori', 'Satuan', 'Lokasi', 'Stok Saat Ini', 'Stok Minimum', 'Status'], ';');
            foreach ($stocks as $s) {
                fputcsv($file, [
                    $s->item?->nama_item,
                    $s->item?->category?->nama_kategori,
                    $s->item?->satuan,
                    $s->lokasi?->nama_cabang,
                    $s->qty,
                    $s->qty_minimum,
                    $s->isBelowMinimum() ? 'Rendah' : 'Normal',
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function exportPergerakanExcel($movements)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pergerakan-stok-' . now()->format('Y-m-d') . '.xls"',
        ];
        $callback = function () use ($movements) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'Barang', 'Tipe', 'Qty', 'Lokasi Asal', 'Lokasi Tujuan', 'Catatan', 'User'], ';');
            foreach ($movements as $m) {
                fputcsv($file, [
                    $m->created_at?->format('d/m/Y H:i'),
                    $m->item?->nama_item,
                    $m->tipe?->value,
                    $m->qty,
                    $m->lokasiAsal?->nama_cabang,
                    $m->lokasiTujuan?->nama_cabang,
                    $m->catatan,
                    $m->user?->name,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
