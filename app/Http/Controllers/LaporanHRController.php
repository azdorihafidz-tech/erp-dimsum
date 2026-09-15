<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\Karyawan;
use App\Models\Penggajian;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanHRController extends Controller
{
    public function absensi(Request $request)
    {
        abort_unless(auth()->user()->can('absensi.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Absensi::withoutGlobalScopes()
            ->with(['karyawan', 'cabang'])
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        if ($cabangId) $query->where('cabang_id', $cabangId);
        if ($request->karyawan_id) $query->where('karyawan_id', $request->karyawan_id);
        if ($request->status) $query->where('status', $request->status);

        $totalHadir = (clone $query)->where('status', 'hadir')->count();
        $totalAlpha = (clone $query)->where('status', 'alpha')->count();
        $totalIzin = (clone $query)->where('status', 'izin')->count();
        $totalSakit = (clone $query)->where('status', 'sakit')->count();

        if ($request->export === 'excel') {
            $absensi = $query->orderBy('tanggal')->get();
            return $this->exportAbsensiExcel($absensi);
        }

        $absensis = $query->orderByDesc('tanggal')->paginate(30)->withQueryString();

        // Rekap per karyawan
        $rekapKaryawan = Absensi::withoutGlobalScopes()
            ->selectRaw('karyawan_id,
                SUM(CASE WHEN status="hadir" THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status="alpha" THEN 1 ELSE 0 END) as alpha,
                SUM(CASE WHEN status="izin" THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status="sakit" THEN 1 ELSE 0 END) as sakit,
                SUM(jam_lembur) as total_lembur')
            ->with('karyawan')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->groupBy('karyawan_id')
            ->get();

        $karyawans = Karyawan::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->where('status', 'aktif')
            ->get();

        return view('laporan.hr.absensi', compact(
            'absensis', 'cabangs', 'cabangId', 'dari', 'sampai',
            'totalHadir', 'totalAlpha', 'totalIzin', 'totalSakit',
            'rekapKaryawan', 'karyawans'
        ));
    }

    public function penggajian(Request $request)
    {
        abort_unless(auth()->user()->can('penggajian.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $periode = $request->periode ?? Carbon::now()->format('Y-m');
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = Penggajian::withoutGlobalScopes()
            ->with(['karyawan', 'cabang'])
            ->where('periode', $periode);
        if ($cabangId) $query->where('cabang_id', $cabangId);

        $totalGaji = (clone $query)->sum('total_gaji');
        $totalKaryawan = (clone $query)->count();
        $sudahBayar = (clone $query)->where('status', 'dibayar')->count();
        $belumBayar = (clone $query)->where('status', '!=', 'dibayar')->count();

        if ($request->export === 'excel') {
            $penggajians = $query->orderBy('karyawan_id')->get();
            return $this->exportPenggajianExcel($penggajians, $periode);
        }

        $penggajians = $query->paginate(30)->withQueryString();

        return view('laporan.hr.penggajian', compact(
            'penggajians', 'cabangs', 'cabangId', 'periode',
            'totalGaji', 'totalKaryawan', 'sudahBayar', 'belumBayar'
        ));
    }

    public function evaluasi(Request $request)
    {
        abort_unless(auth()->user()->can('evaluasi.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $periodId = $request->period_id;

        // Periode evaluasi yang tersedia
        $periods = EvaluationPeriod::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->orderByDesc('tanggal_mulai')
            ->get();

        $evaluations = collect();
        $selectedPeriod = null;

        if ($periodId) {
            $selectedPeriod = EvaluationPeriod::find($periodId);
            $evaluations = Evaluation::withoutGlobalScopes()
                ->with(['karyawan', 'cabang'])
                ->where('evaluation_period_id', $periodId)
                ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                ->orderByDesc('skor_akhir')
                ->get();
        } elseif ($periods->isNotEmpty()) {
            $selectedPeriod = $periods->first();
            $evaluations = Evaluation::withoutGlobalScopes()
                ->with(['karyawan', 'cabang'])
                ->where('evaluation_period_id', $selectedPeriod->id)
                ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
                ->orderByDesc('skor_akhir')
                ->get();
        }

        $rataRataSkor = $evaluations->avg('skor_akhir');
        $totalEvaluasi = $evaluations->count();
        $sangat_baik = $evaluations->filter(fn($e) => $e->predikat?->value === 'sangat_baik')->count();
        $baik = $evaluations->filter(fn($e) => $e->predikat?->value === 'baik')->count();

        return view('laporan.hr.evaluasi', compact(
            'evaluations', 'cabangs', 'cabangId', 'periods', 'selectedPeriod',
            'rataRataSkor', 'totalEvaluasi', 'sangat_baik', 'baik'
        ));
    }

    private function exportAbsensiExcel($absensi)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-absensi-' . now()->format('Y-m-d') . '.xls"',
        ];
        $callback = function () use ($absensi) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Tanggal', 'Karyawan', 'Cabang', 'Status', 'Jam Masuk', 'Jam Keluar', 'Lembur', 'Keterangan'], ';');
            foreach ($absensi as $a) {
                fputcsv($file, [
                    $a->tanggal?->format('d/m/Y'),
                    $a->karyawan?->nama_lengkap,
                    $a->cabang?->nama_cabang,
                    $a->status,
                    $a->jam_masuk,
                    $a->jam_keluar,
                    $a->jam_lembur,
                    $a->keterangan,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function exportPenggajianExcel($penggajians, $periode)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-penggajian-' . $periode . '.xls"',
        ];
        $callback = function () use ($penggajians) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Karyawan', 'Cabang', 'Gaji Pokok', 'Tunjangan', 'Lembur', 'Bonus', 'Potongan', 'Total Gaji', 'Status'], ';');
            foreach ($penggajians as $p) {
                fputcsv($file, [
                    $p->karyawan?->nama_lengkap,
                    $p->cabang?->nama_cabang,
                    $p->gaji_pokok,
                    $p->tunjangan,
                    $p->uang_lembur,
                    $p->bonus,
                    $p->potongan_absensi + $p->potongan_lain,
                    $p->total_gaji,
                    $p->status,
                ], ';');
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }
}
