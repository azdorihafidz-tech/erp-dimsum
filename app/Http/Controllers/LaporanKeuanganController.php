<?php

namespace App\Http\Controllers;

use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanKeuanganController extends Controller
{
    public function labaRugi(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        // Dari/sampai dengan session persistence
        if ($request->filled('dari') || $request->filled('sampai')) {
            $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
            $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
            session(['labarugi_dari' => $dari->toDateString(), 'labarugi_sampai' => $sampai->toDateString()]);
        } else {
            $dari   = Carbon::parse(session('labarugi_dari',   Carbon::now()->startOfMonth()->toDateString()))->startOfDay();
            $sampai = Carbon::parse(session('labarugi_sampai', Carbon::now()->toDateString()))->endOfDay();
        }

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Pemasukan grouped by kategori
        // Catatan: withoutGlobalScope(CabangScope::class) — BUKAN withoutGlobalScopes()
        // tanpa argumen — supaya SoftDeletingScope tetap aktif (mencegah transaksi
        // dari order yang sudah dibatalkan/dihapus ikut ke-SUM sebagai omzet).
        $pemasukanQuery = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->where('tipe', TipeTransaksiKeuangan::Pemasukan)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        if ($cabangId) $pemasukanQuery->where('cabang_id', $cabangId);

        $pemasukan = $pemasukanQuery->selectRaw('kategori, SUM(jumlah) as total')
            ->groupBy('kategori')
            ->get();
        $totalPemasukan = $pemasukan->sum('total');

        // Pengeluaran grouped by kategori
        $pengeluaranQuery = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        if ($cabangId) $pengeluaranQuery->where('cabang_id', $cabangId);

        $pengeluaran = $pengeluaranQuery->selectRaw('kategori, SUM(jumlah) as total')
            ->groupBy('kategori')
            ->get();
        $totalPengeluaran = $pengeluaran->sum('total');

        $labaRugi = $totalPemasukan - $totalPengeluaran;

        // Grafik bulanan 6 bulan terakhir (selalu trailing 6 bulan)
        $grafikLabels = [];
        $grafikPemasukan = [];
        $grafikPengeluaran = [];
        for ($i = 5; $i >= 0; $i--) {
            $bln = Carbon::now()->subMonths($i)->format('Y-m');
            $grafikLabels[] = Carbon::now()->subMonths($i)->translatedFormat('M Y');
            $qMasuk = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
                ->where('tipe', TipeTransaksiKeuangan::Pemasukan)
                ->where('tanggal_transaksi', 'like', $bln . '%');
            if ($cabangId) $qMasuk->where('cabang_id', $cabangId);
            $grafikPemasukan[] = (float) $qMasuk->sum('jumlah');

            $qKeluar = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
                ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
                ->where('tanggal_transaksi', 'like', $bln . '%');
            if ($cabangId) $qKeluar->where('cabang_id', $cabangId);
            $grafikPengeluaran[] = (float) $qKeluar->sum('jumlah');
        }

        if ($request->export === 'excel') {
            return $this->exportLabaRugiExcel($pemasukan, $pengeluaran, $totalPemasukan, $totalPengeluaran, $labaRugi, $dari->toDateString() . '_' . $sampai->toDateString());
        }

        return view('laporan.keuangan.laba-rugi', compact(
            'pemasukan', 'pengeluaran', 'totalPemasukan', 'totalPengeluaran', 'labaRugi',
            'cabangs', 'cabangId', 'dari', 'sampai',
            'grafikLabels', 'grafikPemasukan', 'grafikPengeluaran'
        ));
    }

    public function arusKas(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->with(['cabang'])
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        if ($cabangId) $query->where('cabang_id', $cabangId);

        $totalMasuk = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pemasukan)->sum('jumlah');
        $totalKeluar = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pengeluaran)->sum('jumlah');
        $saldo = $totalMasuk - $totalKeluar;

        if ($request->export === 'excel') {
            $transaksis = $query->orderBy('tanggal_transaksi')->get();
            return $this->exportArusKasExcel($transaksis);
        }

        $transaksis = $query->orderBy('tanggal_transaksi')->paginate(30)->withQueryString();

        return view('laporan.keuangan.arus-kas', compact(
            'transaksis', 'cabangs', 'cabangId', 'dari', 'sampai',
            'totalMasuk', 'totalKeluar', 'saldo'
        ));
    }

    public function harian(Request $request)
    {
        return $this->arusKas($request);
    }

    public function pengeluaran(Request $request)
    {
        return $this->labaRugi($request);
    }

    private function exportLabaRugiExcel($pemasukan, $pengeluaran, $totalPemasukan, $totalPengeluaran, $labaRugi, $periode)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-laba-rugi-' . $periode . '.xls"',
        ];
        $callback = function () use ($pemasukan, $pengeluaran, $totalPemasukan, $totalPengeluaran, $labaRugi) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['LAPORAN LABA RUGI'], ';');
            fputcsv($file, [], ';');
            fputcsv($file, ['PEMASUKAN'], ';');
            fputcsv($file, ['Kategori', 'Total'], ';');
            foreach ($pemasukan as $p) {
                fputcsv($file, [$p->kategori, $p->total], ';');
            }
            fputcsv($file, ['Total Pemasukan', $totalPemasukan], ';');
            fputcsv($file, [], ';');
            fputcsv($file, ['PENGELUARAN'], ';');
            fputcsv($file, ['Kategori', 'Total'], ';');
            foreach ($pengeluaran as $p) {
                fputcsv($file, [$p->kategori, $p->total], ';');
            }
            fputcsv($file, ['Total Pengeluaran', $totalPengeluaran], ';');
            fputcsv($file, [], ';');
            fputcsv($file, ['LABA / RUGI', $labaRugi], ';');
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function exportArusKasExcel($transaksis)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-arus-kas-' . now()->format('Y-m-d') . '.xls"',
        ];
        $callback = function () use ($transaksis) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'No. Transaksi', 'Tipe', 'Kategori', 'Keterangan', 'Jumlah', 'Cabang'], ';');
            foreach ($transaksis as $t) {
                fputcsv($file, [
                    $t->tanggal_transaksi?->format('d/m/Y'),
                    $t->nomor_transaksi,
                    $t->tipe?->label(),
                    $t->kategori?->label(),
                    $t->keterangan,
                    $t->jumlah,
                    $t->cabang?->nama_cabang,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
