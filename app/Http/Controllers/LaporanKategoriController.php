<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\KategoriTransaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanKategoriController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user    = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
        $tipe   = $request->tipe ?: null;     // pemasukan / pengeluaran / null=semua
        $parentId = $request->parent_id ?: null;

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Ambil semua parent kategori untuk filter dropdown
        $parentKategoris = KategoriTransaksi::aktif()->toplevel()->orderBy('urutan')->get(['id', 'nama', 'tipe']);

        // Aggregasi per kategori (dengan parent info)
        $rows = DB::table('transaksi_keuangans as tk')
            ->leftJoin('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->leftJoin('kategori_transaksis as kp', 'kp.id', '=', 'kt.parent_id')
            ->whereNull('tk.deleted_at')
            ->whereBetween('tk.tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('tk.cabang_id', $cabangId))
            ->when($tipe, fn($q) => $q->where('tk.tipe', $tipe))
            ->when($parentId, fn($q) => $q->where('kt.parent_id', $parentId))
            ->selectRaw("
                kt.id          as kategori_id,
                kt.nama        as kategori_nama,
                kt.tipe        as kategori_tipe,
                kt.parent_id   as parent_id,
                kp.nama        as parent_nama,
                tk.tipe        as tipe,
                SUM(tk.jumlah) as total,
                COUNT(*)       as jumlah_transaksi
            ")
            ->groupBy('tk.kategori_id', 'kt.id', 'kt.nama', 'kt.tipe', 'kt.parent_id', 'kp.nama', 'tk.tipe')
            ->orderBy('kp.nama')
            ->orderBy('kt.nama')
            ->get();

        // Kelompokkan: parent → children
        $grouped = $rows->groupBy(fn($r) => $r->parent_nama ?? $r->kategori_nama ?? 'Lainnya');

        $totalPemasukan   = $rows->where('tipe', 'pemasukan')->sum('total');
        $totalPengeluaran = $rows->where('tipe', 'pengeluaran')->sum('total');

        // Pie chart data (pengeluaran per parent)
        $pieData = $rows->where('tipe', 'pengeluaran')
            ->groupBy(fn($r) => $r->parent_nama ?? $r->kategori_nama ?? 'Lainnya')
            ->map(fn($g) => $g->sum('total'))
            ->sortDesc();

        $pieLabels = $pieData->keys()->toArray();
        $pieValues = $pieData->values()->map(fn($v) => (float)$v)->toArray();

        if ($request->export === 'excel') {
            return $this->exportExcel($rows, $dari, $sampai);
        }

        return view('laporan.kategori', compact(
            'grouped', 'rows', 'cabangs', 'cabangId', 'dari', 'sampai', 'tipe', 'parentId',
            'parentKategoris', 'totalPemasukan', 'totalPengeluaran', 'pieLabels', 'pieValues'
        ));
    }

    private function exportExcel($rows, Carbon $dari, Carbon $sampai)
    {
        $headers = [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-kategori-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xls"',
        ];
        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Parent Kategori', 'Kategori', 'Tipe', 'Jumlah Transaksi', 'Total'], ';');
            foreach ($rows as $r) {
                fputcsv($file, [
                    $r->parent_nama ?? '-',
                    $r->kategori_nama ?? 'Lainnya',
                    $r->tipe,
                    $r->jumlah_transaksi,
                    $r->total,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
