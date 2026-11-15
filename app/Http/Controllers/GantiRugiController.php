<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\GantiRugi;
use App\Models\StokOpname;
use App\Services\GantiRugiService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Ganti rugi karyawan atas barang Hilang (Stok Opname Tahap D). */
class GantiRugiController extends Controller
{
    public function __construct(private GantiRugiService $service) {}

    private function gagal(RuntimeException $e)
    {
        if ($e instanceof HttpExceptionInterface) {
            throw $e;
        }

        return back()->withInput()->with('error', $e->getMessage());
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('stok_opname.ganti_rugi'), 403);
        $lokasi = $user->canAccessAllBranches()
            ? Cabang::where('is_active', true)->orderBy('nama_cabang')->get()
            : $user->cabangs()->orderBy('nama_cabang')->get();
        $aktif = CabangAktif::id();
        $filter = $request->filled('cabang_id') ? $request->integer('cabang_id') : ($aktif && $lokasi->contains('id', $aktif) ? $aktif : null);
        $ids = $filter && $lokasi->contains('id', $filter) ? [$filter] : $lokasi->pluck('id')->all();
        $status = $request->input('status', 'berjalan');

        $rows = GantiRugi::with(['karyawan', 'cabang', 'opname', 'kas', 'potongan.penggajian'])
            ->whereIn('cabang_id', $ids)
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        $sisa = (float) GantiRugi::whereIn('cabang_id', $ids)->where('status', 'berjalan')->selectRaw('SUM(nilai - terbayar) as s')->value('s');

        return view('ganti-rugi.index', compact('rows', 'lokasi', 'filter', 'status', 'sisa'));
    }

    public function store(Request $request, StokOpname $stokOpname)
    {
        $request->validate([
            'karyawan_id'    => 'required|integer',
            'nilai'          => 'required',
            'alasan_nilai'   => 'nullable|string|max:255',
            'cara'           => 'required|in:tunai,potong_gaji',
            'jumlah_cicilan' => 'nullable|integer|min:1|max:12',
            'kas_id'         => 'nullable|integer',
        ]);
        try {
            $g = $this->service->buat($stokOpname, $request->only(['karyawan_id', 'nilai', 'alasan_nilai', 'cara', 'jumlah_cicilan', 'kas_id']), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e);
        }

        return back()->with('success', 'Ganti rugi ' . $g->karyawan?->nama_lengkap . ' tercatat (' . $g->labelCara() . ').');
    }

    public function batal(Request $request, GantiRugi $gantiRugi)
    {
        $request->validate(['alasan' => 'required|string|max:255'], ['alasan.required' => 'Alasan pembatalan wajib diisi.']);
        try {
            $this->service->batal($gantiRugi, $request->input('alasan'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e);
        }

        return back()->with('success', 'Ganti rugi dibatalkan.');
    }
}
