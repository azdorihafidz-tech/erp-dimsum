<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AssetMaintenance;
use App\Models\AssetMutation;
use App\Models\Cabang;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanAsetController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();
        $kategories = AssetCategory::orderBy('nama_kategori')->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Asset::with(['kategori', 'lokasi'])
            ->when($lokasiId, fn($q) => $q->where('lokasi_id', $lokasiId))
            ->when($request->kategori_id, fn($q) => $q->where('kategori_aset_id', $request->kategori_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $totalAset = (clone $query)->count();
        $totalNilaiBuku = (clone $query)->sum('nilai_buku');
        $totalHargaPerolehan = (clone $query)->sum('harga_perolehan');
        $totalPenyusutan = $totalHargaPerolehan - $totalNilaiBuku;

        if ($request->export === 'excel') {
            $assets = $query->orderBy('nama_aset')->get();
            return $this->exportExcel($assets);
        }

        $assets = $query->orderBy('nama_aset')->paginate(30)->withQueryString();

        return view('laporan.aset.index', compact(
            'assets', 'cabangs', 'kategories', 'lokasiId',
            'totalAset', 'totalNilaiBuku', 'totalHargaPerolehan', 'totalPenyusutan'
        ));
    }

    public function penyusutan(Request $request)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $periode = $request->periode ?? Carbon::now()->format('Y-m');

        $query = AssetDepreciation::with(['asset', 'asset.kategori', 'asset.lokasi'])
            ->where('periode', $periode)
            ->when($lokasiId, function ($q) use ($lokasiId) {
                $q->whereHas('asset', fn($q2) => $q2->where('lokasi_id', $lokasiId));
            });

        $totalPenyusutanPeriode = (clone $query)->sum('jumlah_penyusutan');
        $totalAsetTerdepresiasi = (clone $query)->count();

        if ($request->export === 'excel') {
            $depreciations = $query->get();
            return $this->exportPenyusutanExcel($depreciations, $periode);
        }

        $depreciations = $query->paginate(30)->withQueryString();

        return view('laporan.aset.penyusutan', compact(
            'depreciations', 'cabangs', 'lokasiId', 'periode',
            'totalPenyusutanPeriode', 'totalAsetTerdepresiasi'
        ));
    }

    public function maintenance(Request $request)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $lokasiId = $request->lokasi_id;
        if (!$lokasiId && !$user->canAccessAllBranches()) {
            $lokasiId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = AssetMaintenance::with(['asset', 'asset.lokasi'])
            ->whereBetween('tanggal_maintenance', [$dari->toDateString(), $sampai->toDateString()])
            ->when($lokasiId, function ($q) use ($lokasiId) {
                $q->whereHas('asset', fn($q2) => $q2->where('lokasi_id', $lokasiId));
            });

        $totalBiaya = (clone $query)->sum('biaya');
        $totalKejadian = (clone $query)->count();

        $maintenances = $query->orderByDesc('tanggal_maintenance')->paginate(30)->withQueryString();

        return view('laporan.aset.index', compact(
            'cabangs', 'lokasiId', 'dari', 'sampai', 'totalBiaya', 'totalKejadian'
        ))->with('maintenances', $maintenances)->with('viewMode', 'maintenance');
    }

    public function mutasi(Request $request)
    {
        return $this->index($request);
    }

    private function exportExcel($assets)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-aset-' . now()->format('Y-m-d') . '.xls"',
        ];
        $callback = function () use ($assets) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Kode Aset', 'Nama Aset', 'Kategori', 'Lokasi', 'Tgl Perolehan', 'Harga Perolehan', 'Nilai Buku', 'Kondisi', 'Status'], ';');
            foreach ($assets as $a) {
                fputcsv($file, [
                    $a->kode_aset,
                    $a->nama_aset,
                    $a->kategori?->nama_kategori,
                    $a->lokasi?->nama_cabang,
                    $a->tanggal_perolehan?->format('d/m/Y'),
                    $a->harga_perolehan,
                    $a->nilai_buku,
                    $a->kondisi?->value,
                    $a->status?->value,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function exportPenyusutanExcel($depreciations, $periode)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-penyusutan-' . $periode . '.xls"',
        ];
        $callback = function () use ($depreciations) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Kode Aset', 'Nama Aset', 'Kategori', 'Lokasi', 'Nilai Buku Awal', 'Penyusutan', 'Akumulasi', 'Nilai Buku Akhir'], ';');
            foreach ($depreciations as $d) {
                fputcsv($file, [
                    $d->asset?->kode_aset,
                    $d->asset?->nama_aset,
                    $d->asset?->kategori?->nama_kategori,
                    $d->asset?->lokasi?->nama_cabang,
                    $d->nilai_buku_awal,
                    $d->jumlah_penyusutan,
                    $d->akumulasi_penyusutan,
                    $d->nilai_buku_akhir,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
