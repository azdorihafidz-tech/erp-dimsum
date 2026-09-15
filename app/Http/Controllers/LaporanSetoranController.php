<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanSetoranController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.view'), 403);

        $user    = auth()->user();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
        $status = $request->status ?: null;

        $cabangId = $request->cabang_id ?: null;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Base query — SETOR-OUT (outgoing setoran dari cabang ke pusat)
        // withoutGlobalScopes() agar soft-deleted (ditolak/dibatalkan) juga muncul
        $base = fn() => TransaksiKeuangan::withoutGlobalScopes()
            ->join('kategori_transaksis as kt', 'kt.id', '=', 'transaksi_keuangans.kategori_id')
            ->where('kt.kode', 'SETOR-OUT')
            ->whereBetween('transaksi_keuangans.tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('transaksi_keuangans.cabang_id', $cabangId))
            ->select('transaksi_keuangans.*');

        // Stats (all statuses combined)
        $totalJumlah  = $base()->sum('transaksi_keuangans.jumlah');
        $totalDiterima = $base()->where('transaksi_keuangans.status_setoran', 'diterima')
            ->whereNull('transaksi_keuangans.deleted_at')->sum('transaksi_keuangans.jumlah');
        $totalPending  = $base()->where('transaksi_keuangans.status_setoran', 'menunggu_diterima')
            ->whereNull('transaksi_keuangans.deleted_at')->sum('transaksi_keuangans.jumlah');
        $totalDitolak  = $base()->whereNotNull('transaksi_keuangans.deleted_at')->sum('transaksi_keuangans.jumlah');

        // Apply status filter
        $filtered = $base();
        if ($status === 'menunggu') {
            $filtered->where('transaksi_keuangans.status_setoran', 'menunggu_diterima')
                     ->whereNull('transaksi_keuangans.deleted_at');
        } elseif ($status === 'diterima') {
            $filtered->where('transaksi_keuangans.status_setoran', 'diterima')
                     ->whereNull('transaksi_keuangans.deleted_at');
        } elseif ($status === 'ditolak') {
            $filtered->whereNotNull('transaksi_keuangans.deleted_at')
                     ->where('transaksi_keuangans.status_setoran', 'ditolak');
        } elseif ($status === 'dibatalkan') {
            $filtered->whereNotNull('transaksi_keuangans.deleted_at')
                     ->where('transaksi_keuangans.status_setoran', 'dibatalkan');
        }

        if ($request->export === 'excel') {
            return $this->exportExcel(
                $filtered->with(['cabang:id,nama_cabang', 'kas:id,nama_kas'])
                    ->orderBy('transaksi_keuangans.tanggal_transaksi')
                    ->get(),
                $dari, $sampai
            );
        }

        $setorans = $filtered
            ->with(['cabang:id,nama_cabang', 'kas:id,nama_kas', 'diterimaOleh:id,name'])
            ->orderByDesc('transaksi_keuangans.tanggal_transaksi')
            ->paginate(20)
            ->withQueryString();

        // Per-cabang breakdown
        $perCabang = DB::table('transaksi_keuangans as tk')
            ->join('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->join('cabangs as c', 'c.id', '=', 'tk.cabang_id')
            ->where('kt.kode', 'SETOR-OUT')
            ->whereBetween('tk.tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('tk.cabang_id', $cabangId))
            ->selectRaw('c.nama_cabang, SUM(tk.jumlah) as total, COUNT(*) as jumlah_setoran')
            ->groupBy('tk.cabang_id', 'c.nama_cabang')
            ->orderByDesc('total')
            ->get();

        // Grafik per cabang
        $grafikLabels = $perCabang->pluck('nama_cabang')->toArray();
        $grafikData   = $perCabang->pluck('total')->map(fn($v) => (float)$v)->toArray();

        return view('laporan.setoran', compact(
            'setorans', 'cabangs', 'cabangId', 'dari', 'sampai', 'status',
            'totalJumlah', 'totalDiterima', 'totalPending', 'totalDitolak',
            'perCabang', 'grafikLabels', 'grafikData'
        ));
    }

    private function exportExcel($rows, Carbon $dari, Carbon $sampai)
    {
        $headers = [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-setoran-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xls"',
        ];
        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'No. Transaksi', 'Cabang Asal', 'Kas Asal', 'Jumlah', 'Status', 'Keterangan'], ';');
            foreach ($rows as $r) {
                $status = match(true) {
                    !is_null($r->deleted_at) => $r->status_setoran ?? 'dihapus',
                    default                  => $r->status_setoran ?? '-',
                };
                fputcsv($file, [
                    optional($r->tanggal_transaksi)->format('d/m/Y'),
                    $r->nomor_transaksi,
                    $r->cabang?->nama_cabang,
                    $r->kas?->nama_kas,
                    $r->jumlah,
                    $status,
                    $r->keterangan,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
