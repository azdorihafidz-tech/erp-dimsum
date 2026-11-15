<?php

namespace App\Http\Controllers;

use App\Enums\PenyebabSelisihStok;
use App\Models\Cabang;
use App\Models\ItemCategory;
use App\Models\StokOpname;
use App\Models\User;
use App\Services\StokOpnameService;
use App\Services\StokService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stok Opname (RANCANGAN_STOK_OPNAME.md) — controller tipis, semua aturan di
 * StokOpnameService. Filter lokasi manual (CLAUDE.md 3.4).
 */
class StokOpnameController extends Controller
{
    public function __construct(private StokOpnameService $service, private StokService $stok) {}

    private function izinMenu(): bool
    {
        $u = auth()->user();

        return $u->hasPermission('stok_opname.view') || $u->hasPermission('stok_opname.hitung')
            || $u->hasPermission('stok_opname.ajukan') || $u->hasPermission('stok_opname.setujui');
    }

    /** Lokasi yang boleh dilihat user (filter header bila dipilih). */
    private function lokasiBoleh()
    {
        $user = auth()->user();

        return $user->canAccessAllBranches()
            ? Cabang::where('is_active', true)->orderBy('nama_cabang')->get()
            : $user->cabangs()->where('cabangs.is_active', true)->orderBy('nama_cabang')->get();
    }

    private function gagal(RuntimeException $e, Request $request)
    {
        // abort(403) dari service juga RuntimeException — teruskan apa adanya
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            throw $e;
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }

        return back()->withInput()->with('error', $e->getMessage());
    }

    public function index(Request $request)
    {
        abort_unless($this->izinMenu(), 403);
        $lokasi = $this->lokasiBoleh();
        $aktif = CabangAktif::id();
        $filter = $request->integer('cabang_id') ?: ($aktif && $lokasi->contains('id', $aktif) ? $aktif : null);

        $opnames = StokOpname::with(['cabang', 'pembuat', 'penyetuju'])
            ->whereIn('cabang_id', $filter && $lokasi->contains('id', $filter) ? [$filter] : $lokasi->pluck('id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->withCount(['items as jumlah_barang' => fn ($q) => $q->where('dikeluarkan', false)])
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('stok-opname.index', compact('opnames', 'lokasi', 'filter'));
    }

    public function dashboard()
    {
        abort_unless($this->izinMenu(), 403);
        $d = app(\App\Services\StokOpnameDashboardService::class)->hitung($this->lokasiBoleh());

        return view('stok-opname.dashboard', compact('d'));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('stok_opname.ajukan'), 403);
        $lokasi = $this->lokasiBoleh();
        $aktif = CabangAktif::id();
        $lokasiId = (int) (old('cabang_id') ?: ($aktif && $lokasi->contains('id', $aktif) ? $aktif : $lokasi->first()?->id));

        $barangPerLokasi = [];
        $penghitungPerLokasi = [];
        $calonPenghitung = User::pelanggan()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('stok_opname.hitung'));
        foreach ($lokasi as $l) {
            $terkunci = StokOpnameService::barangTerkunci($l->id);
            $barangPerLokasi[$l->id] = StokOpnameService::barangLokasi($l->id)->orderBy('items.nama_item')
                ->get(['items.id', 'items.kode_item', 'items.nama_item', 'items.item_category_id', 'items.satuan'])
                ->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama_item, 'kode' => $b->kode_item, 'kategori' => $b->item_category_id,
                    'terkunci' => $terkunci[$b->id] ?? null])->values();
            $penghitungPerLokasi[$l->id] = $calonPenghitung->filter(fn (User $u) => StokOpnameService::bolehAksesLokasi($u, $l->id))
                ->map(fn ($u) => ['id' => $u->id, 'nama' => $u->name])->values();
        }

        return view('stok-opname.create', [
            'lokasi'              => $lokasi,
            'lokasiId'            => $lokasiId,
            'kategori'            => ItemCategory::orderBy('nama_kategori')->get(),
            'barangPerLokasi'     => $barangPerLokasi,
            'penghitungPerLokasi' => $penghitungPerLokasi,
            'hitungButa'          => (bool) StokOpnameService::setting()->opname_hitung_buta,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('stok_opname.ajukan'), 403);
        $data = $request->validate([
            'cabang_id'     => 'required|integer|exists:cabangs,id',
            'cakupan'       => 'required|in:semua,kategori,barang',
            'cakupan_ids'   => 'nullable|array',
            'cakupan_ids.*' => 'integer',
            'penghitung'    => 'nullable|array',
            'penghitung.*'  => 'integer|exists:users,id',
            'catatan'       => 'nullable|string|max:500',
        ]);

        try {
            $opname = $this->service->mulai($data, auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return redirect()->route('stok-opname.show', $opname)->with('success', "Stok opname {$opname->nomor} dimulai. Silakan hitung barangnya.");
    }

    public function show(StokOpname $stokOpname)
    {
        $user = auth()->user();
        abort_unless($this->service->bolehLihat($user, $stokOpname), 403);
        $o = $stokOpname->load(['cabang', 'pembuat', 'pengaju', 'penyetuju', 'penolak', 'pembatal', 'penghitung']);
        $rows = $o->items()->with(['item.category', 'rincian', 'penghitung'])->get()
            ->sortBy(fn ($r) => mb_strtolower((string) $r->item?->nama_item))->values();

        $lihatSistem = $this->service->bolehLihatSistem($user, $o);
        $perkiraan = [];
        if ($lihatSistem && $o->status !== StokOpname::DISETUJUI) {
            foreach ($rows as $r) {
                $s = $r->selisih();
                if ($s !== null && abs($s) > 0.0005 && ! $r->dikeluarkan) {
                    $perkiraan[$r->id] = $s < 0
                        ? -$this->stok->perkiraanNilaiKeluar($r->item_id, $o->cabang_id, abs($s))
                        : round($s * $this->stok->hargaPokokRataRata($r->item_id, $o->cabang_id), 2);
                }
            }
        }

        return view('stok-opname.show', [
            'o'            => $o,
            'rows'         => $rows,
            'lihatSistem'  => $lihatSistem,
            'bolehHitung'  => $this->service->bolehHitung($user, $o),
            'bolehPeriksa' => $this->service->bolehPeriksa($user, $o),
            'bolehAjukan'  => $user->hasPermission('stok_opname.ajukan') && $this->service->bolehPeriksa($user, $o),
            'bolehSetujui' => $this->service->bolehSetujui($user, $o, false),
            'ikutHitung'   => $this->service->ikutMenghitung($user, $o) && ! StokOpnameService::isOwner($user),
            'bolehBatal'   => $user->hasPermission('stok_opname.batal') && StokOpnameService::bolehAksesLokasi($user, $o->cabang_id),
            'perkiraan'    => $perkiraan,
            'bolehUnduh'   => $this->service->bolehUnduhBeritaAcara($user, $o),
            'gantiRugi'    => $this->dataGantiRugi($o),
            'masalah'      => $o->status === StokOpname::MENGHITUNG && $lihatSistem ? $this->service->masalahPengajuan($o) : [],
            'penyebabKurang' => PenyebabSelisihStok::untukKurang(),
            'batasFoto'    => StokOpnameService::batasFotoHilang(),
        ]);
    }

    /** Ganti rugi karyawan untuk barang Hilang (hanya opname disetujui). */
    private function dataGantiRugi(StokOpname $o): ?array
    {
        if ($o->status !== StokOpname::DISETUJUI || ! \App\Services\GantiRugiService::siap()) {
            return null;
        }
        $svc = app(\App\Services\GantiRugiService::class);
        $r = $svc->ringkasOpname($o);
        if ($r['hilang'] <= 0) {
            return null;
        }

        return $r + [
            'daftar'   => \App\Models\GantiRugi::with(['karyawan', 'kas'])->where('stok_opname_id', $o->id)->orderBy('id')->get(),
            'kelola'   => $svc->bolehKelola(auth()->user(), $o->cabang_id),
            'karyawan' => \App\Services\GantiRugiService::karyawanUntuk($o->cabang_id),
            'kas'      => \App\Models\Kas::where('cabang_id', $o->cabang_id)->where('is_active', true)->orderBy('nama_kas')->get(['id', 'nama_kas']),
        ];
    }

    private function jsonBaris(StokOpname $o, $row)
    {
        $lihat = $this->service->bolehLihatSistem(auth()->user(), $o);

        return response()->json([
            'ok'          => true,
            'item_id'     => $row->item_id,
            'qty_fisik'   => $row->qty_fisik === null ? null : (float) $row->qty_fisik,
            'teks_fisik'  => StokOpnameService::angka($row->qty_fisik === null ? null : (float) $row->qty_fisik),
            'qty_sistem'  => $lihat && $row->qty_sistem !== null ? (float) $row->qty_sistem : null,
            'selisih'     => $lihat ? $row->selisih() : null,
            'nama'        => $row->item?->nama_item,
            'dihitung'    => $row->sudahDihitung(),
            'jam'         => $row->dihitung_at?->format('H:i'),
        ]);
    }

    public function hitung(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['item_id' => 'required|integer', 'qty' => 'nullable']);
        try {
            $row = $this->service->hitung($stokOpname, $request->integer('item_id'), $request->input('qty'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return $request->expectsJson() ? $this->jsonBaris($stokOpname, $row) : back()->with('success', 'Jumlah tersimpan.');
    }

    public function scan(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['kode' => 'required|string|max:50']);
        try {
            $row = $this->service->scan($stokOpname, $request->input('kode'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return $request->expectsJson() ? $this->jsonBaris($stokOpname, $row) : back()->with('success', $row->item?->nama_item . ' +1');
    }

    public function hitungUlang(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['item_id' => 'required|integer']);
        try {
            $row = $this->service->mintaHitungUlang($stokOpname, $request->integer('item_id'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', "{$row->item?->nama_item} ditandai hitung ulang.");
    }

    public function keluarkan(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['item_id' => 'required|integer', 'keluar' => 'required|boolean', 'alasan' => 'nullable|string|max:255']);
        try {
            $row = $this->service->keluarkan($stokOpname, $request->integer('item_id'), $request->boolean('keluar'), $request->input('alasan'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', $row->item?->nama_item . ($row->dikeluarkan ? ' dikeluarkan dari opname.' : ' dimasukkan lagi ke opname.'));
    }

    public function rincian(Request $request, StokOpname $stokOpname)
    {
        $request->validate([
            'item_id'              => 'required|integer',
            'rincian'              => 'required|array|min:1|max:10',
            'rincian.*.penyebab'   => 'nullable|string',
            'rincian.*.qty'        => 'nullable',
            'rincian.*.catatan'    => 'nullable|string|max:255',
            'rincian.*.rincian_id' => 'nullable|integer',
            'rincian.*.foto'       => 'nullable|image|max:5120',
        ], ['rincian.*.foto.image' => 'Foto harus berupa gambar.', 'rincian.*.foto.max' => 'Ukuran foto maksimal 5 MB.']);

        $rows = [];
        foreach ($request->input('rincian', []) as $i => $r) {
            $r['foto'] = $request->file("rincian.$i.foto");
            $rows[] = $r;
        }
        try {
            $row = $this->service->simpanRincian($stokOpname, $request->integer('item_id'), $rows, auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', "Rincian penyebab {$row->item?->nama_item} tersimpan.");
    }

    public function ajukan(Request $request, StokOpname $stokOpname)
    {
        try {
            $this->service->ajukan($stokOpname, auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', "Stok opname {$stokOpname->nomor} diajukan. Menunggu persetujuan.");
    }

    public function setujui(Request $request, StokOpname $stokOpname)
    {
        try {
            $o = $this->service->setujui($stokOpname, auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', "Stok opname {$o->nomor} disetujui. Stok & keuangan sudah disesuaikan.");
    }

    public function tolak(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['alasan' => 'required|string|max:500'], ['alasan.required' => 'Alasan penolakan wajib diisi.']);
        try {
            $this->service->tolak($stokOpname, $request->input('alasan'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return back()->with('success', "Stok opname {$stokOpname->nomor} ditolak dan kembali ke tahap hitung.");
    }

    /** Berita Acara Stok Opname (?format=pdf|excel). Sebelum disetujui bertanda SEMENTARA. */
    public function beritaAcara(Request $request, StokOpname $stokOpname)
    {
        $user = auth()->user();
        abort_unless($this->service->bolehUnduhBeritaAcara($user, $stokOpname), 403,
            'Berita acara bisa diunduh setelah opname selesai (penghitung buta tidak melihat stok sistem selama opname berjalan).');
        $d = $this->service->dataBeritaAcara($stokOpname);
        $nama = 'Berita-Acara-' . $stokOpname->nomor . ($d['final'] ? '' : '-SEMENTARA');

        if ($request->input('format') === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BeritaAcaraStokOpnameExport($d, $user->name), $nama . '.xlsx');
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('stok-opname.berita-acara-pdf', [
            'd'             => $d,
            'judulLaporan'  => 'Berita Acara Stok Opname',
            'filterInfo'    => ['Nomor' => $stokOpname->nomor, 'Lokasi' => $stokOpname->cabang?->nama_cabang ?? '-'],
            'footerDicetak' => 'Dicetak oleh: ' . $user->name . ' pada ' . now()->format('d/m/Y H:i') . ' WIB',
        ])->setPaper('a4', 'portrait')->download($nama . '.pdf');
    }

    public function batal(Request $request, StokOpname $stokOpname)
    {
        $request->validate(['alasan' => 'required|string|max:500'], ['alasan.required' => 'Alasan pembatalan wajib diisi.']);
        try {
            $this->service->batal($stokOpname, $request->input('alasan'), auth()->user());
        } catch (RuntimeException $e) {
            return $this->gagal($e, $request);
        }

        return redirect()->route('stok-opname.index')->with('success', "Stok opname {$stokOpname->nomor} dibatalkan.");
    }
}
