<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\StokRusak;
use App\Models\StokRusakTindakan;
use App\Models\Supplier;
use App\Services\StokOpnameService;
use App\Services\StokRusakService;
use App\Support\CabangAktif;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Stok Rusak (Stok Opname Tahap C). Filter lokasi manual (CLAUDE.md 3.4). */
class StokRusakController extends Controller
{
    public function __construct(private StokRusakService $service) {}

    private function izin(): bool
    {
        $u = auth()->user();

        return $u->hasPermission('stok_opname.stok_rusak') || $u->hasPermission('stok_opname.view');
    }

    public function index(Request $request)
    {
        abort_unless($this->izin(), 403);
        $user = auth()->user();
        $lokasi = $user->canAccessAllBranches()
            ? Cabang::where('is_active', true)->orderBy('nama_cabang')->get()
            : $user->cabangs()->orderBy('nama_cabang')->get();
        $aktif = CabangAktif::id();
        $filter = $request->filled('cabang_id') ? $request->integer('cabang_id') : ($aktif && $lokasi->contains('id', $aktif) ? $aktif : null);
        $ids = $filter && $lokasi->contains('id', $filter) ? [$filter] : $lokasi->pluck('id')->all();
        $status = $request->input('status', 'tertahan');

        $rows = StokRusak::with(['item', 'cabang', 'rincian.opnameItem.opname', 'tindakan.user', 'tindakan.kas'])
            ->whereIn('cabang_id', $ids)
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->orderByDesc('tanggal')->orderByDesc('id')->paginate(30)->withQueryString();

        $ringkas = StokRusak::whereIn('cabang_id', $ids)->where('qty_sisa', '>', 0)
            ->selectRaw('COUNT(*) as baris, SUM(qty_sisa * harga_pokok) as nilai, MIN(tanggal) as tertua')->first();

        return view('stok-rusak.index', [
            'rows'      => $rows,
            'lokasi'    => $lokasi,
            'filter'    => $filter,
            'status'    => $status,
            'ringkas'   => $ringkas,
            'kas'       => collect($ids)->mapWithKeys(fn ($id) => [$id => StokRusakService::kasLokasi($id)->map(fn ($k) => ['id' => $k->id, 'nama' => $k->nama_kas])->values()]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('nama_supplier')->get(['id', 'nama_supplier']),
        ]);
    }

    public function tindak(Request $request, StokRusak $stokRusak)
    {
        $request->validate([
            'jenis'       => 'required|string',
            'qty'         => 'required',
            'uang'        => 'nullable',
            'kas_id'      => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'nomor_po'    => 'nullable|string|max:60',
            'saksi'       => 'nullable|string|max:150',
            'catatan'     => 'nullable|string|max:255',
            'foto'        => 'nullable|image|max:5120',
        ], ['foto.image' => 'Foto harus berupa gambar.', 'foto.max' => 'Ukuran foto maksimal 5 MB.']);

        try {
            $t = $this->service->tindak($stokRusak, $request->only(['jenis', 'qty', 'uang', 'kas_id', 'supplier_id', 'nomor_po', 'saksi', 'catatan']) + ['foto' => $request->file('foto')], auth()->user());
        } catch (RuntimeException $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        $pesan = StokRusakService::LABEL_TINDAKAN[$t->jenis] . ' tercatat.';
        if ($t->nomor_ba) {
            $pesan .= ' Berita Acara Pemusnahan ' . $t->nomor_ba . ' siap diunduh.';
        }

        return back()->with('success', $pesan);
    }

    /** Berita Acara Pemusnahan (PDF). */
    public function beritaAcara(StokRusakTindakan $tindakan)
    {
        abort_unless($this->izin(), 403);
        abort_unless($tindakan->jenis === 'musnahkan', 404);
        $s = $tindakan->stokRusak()->with(['item', 'cabang', 'rincian.opnameItem.opname'])->firstOrFail();
        abort_unless(StokOpnameService::bolehAksesLokasi(auth()->user(), $s->cabang_id), 403);

        return Pdf::loadView('stok-rusak.berita-acara-pdf', [
            't'             => $tindakan->load('user'),
            's'             => $s,
            'judulLaporan'  => 'Berita Acara Pemusnahan',
            'filterInfo'    => ['Nomor' => $tindakan->nomor_ba, 'Lokasi' => $s->cabang?->nama_cabang ?? '-'],
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->format('d/m/Y H:i') . ' WIB',
        ])->setPaper('a4', 'portrait')->download('Berita-Acara-Pemusnahan-' . $tindakan->nomor_ba . '.pdf');
    }
}
