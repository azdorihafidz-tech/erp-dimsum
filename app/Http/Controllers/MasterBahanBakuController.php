<?php

namespace App\Http\Controllers;

use App\Http\Requests\BahanBakuRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

/**
 * Tahap 2.5 D'mentai — menu "Bahan Baku & Kemasan": form SIMPLE (tanpa
 * foto/resep/varian) untuk items bertipe bahan_baku/kemasan/tambahan_gratis.
 * Beda tabel? TIDAK — tetap 1 tabel `items`, cuma difilter+form terpisah
 * dari Master Produk Jual (lihat CLAUDE.md Tahap 2.5).
 */
class MasterBahanBakuController extends Controller
{
    private const TIPE_SCOPE = ['bahan_baku', 'kemasan', 'tambahan_gratis'];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.view'), 403);

        $query = Item::with('category')->whereIn('tipe', self::TIPE_SCOPE)->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_item', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('tipe') && in_array($request->tipe, self::TIPE_SCOPE, true)) {
            $query->where('tipe', $request->tipe);
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis'];

        $stats = [
            'total'           => Item::whereIn('tipe', self::TIPE_SCOPE)->count(),
            'bahan_baku'      => Item::where('tipe', 'bahan_baku')->count(),
            'kemasan'         => Item::where('tipe', 'kemasan')->count(),
            'tambahan_gratis' => Item::where('tipe', 'tambahan_gratis')->count(),
        ];

        return view('master.bahan-baku.index', compact('items', 'categories', 'tipes', 'stats'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.bahan_baku.create'), 403);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $cabangList = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('master.bahan-baku.create', compact('categories', 'cabangList'));
    }

    public function store(BahanBakuRequest $request)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.create'), 403);

        $data = $request->safe()->except(['stok_awal', 'harga_cabang', 'minimum_cabang']);
        $data['kode_item']  = strtoupper($data['kode_item']);
        $data['is_active']  = $request->boolean('is_active', true);
        // Sprint 4.34 — checkbox-style: absen (unchecked) → false. Form create
        // UI default checked, jadi kalau user tidak uncheck → kirim '1' → true.
        $data['track_stok'] = $request->boolean('track_stok');

        $item = Item::create($data);
        $this->syncHargaCabang($item, (array) $request->input('harga_cabang', []));

        $minimumCabang = (array) $request->input('minimum_cabang', []);
        foreach ((array) $request->input('stok_awal', []) as $cabangId => $qty) {
            $qty = (float) $qty;
            if ($qty <= 0) continue;

            $min = trim((string) ($minimumCabang[$cabangId] ?? '')) !== '' ? (float) $minimumCabang[$cabangId] : ($item->qty_minimum ?? 0);
            Stock::updateOrCreate(
                ['item_id' => $item->id, 'lokasi_id' => (int) $cabangId],
                ['qty' => $qty, 'qty_minimum' => $min]
            );
        }
        $this->syncMinimumCabang($item, $minimumCabang);

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$item->nama_item} berhasil ditambahkan.");
    }

    public function edit(Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $cabangList = Cabang::aktif()->orderBy('nama_cabang')->get();
        $bahanBaku->load('itemCabang');
        $stokLokasi = Stock::withTrashed()->where('item_id', $bahanBaku->id)->get()->keyBy('lokasi_id');

        return view('master.bahan-baku.edit', ['item' => $bahanBaku, 'categories' => $categories, 'cabangList' => $cabangList, 'stokLokasi' => $stokLokasi]);
    }

    public function update(BahanBakuRequest $request, Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);

        $data = $request->safe()->except(['stok_awal', 'harga_cabang', 'minimum_cabang']);
        $data['kode_item']  = strtoupper($data['kode_item']);
        $data['is_active']  = $request->boolean('is_active', true);
        // Sprint 4.34 — checkbox-style konsisten dgn store(): absen → false.
        $data['track_stok'] = $request->boolean('track_stok');

        $bahanBaku->update($data);
        $this->syncHargaCabang($bahanBaku, (array) $request->input('harga_cabang', []));
        $this->syncMinimumCabang($bahanBaku, (array) $request->input('minimum_cabang', []));

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$bahanBaku->nama_item} berhasil diperbarui.");
    }

    /**
     * Simpan harga beli per outlet ke item_cabang.harga_beli (kolom beda dari
     * harga_override = harga JUAL produk). Baris baru dibuat is_active=true
     * supaya ketersediaan item di POS (tambahan_gratis) tidak berubah.
     * Kosong = null (pakai Harga Standar). CLAUDE.md 4.38.
     */
    private function syncHargaCabang(Item $item, array $hargaCabang): void
    {
        foreach ($hargaCabang as $cabangId => $harga) {
            $harga = trim((string) $harga) === '' ? null : (float) $harga;
            $row = \App\Models\ItemCabang::where('item_id', $item->id)->where('cabang_id', (int) $cabangId)->first();

            if ($row) {
                $row->update(['harga_beli' => $harga]);
            } elseif ($harga !== null) {
                \App\Models\ItemCabang::create([
                    'item_id' => $item->id, 'cabang_id' => (int) $cabangId, 'harga_beli' => $harga, 'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Simpan Qty Minimum per lokasi ke stocks.qty_minimum (CLAUDE.md 4.38-E).
     * Qty stok TIDAK disentuh. Kosong = biarkan (outlet yang belum punya baris
     * stok nanti memakai nilai awal items.qty_minimum). Baris stok baru (qty 0)
     * hanya dibuat kalau minimum > 0 supaya alert stok outlet langsung aktif.
     * Baris yang soft-deleted ikut diperbarui (tidak di-restore) supaya tidak
     * bentrok unique (item_id, lokasi_id).
     */
    private function syncMinimumCabang(Item $item, array $minimumCabang): void
    {
        $lokasiValid = Cabang::pluck('id')->all();
        foreach ($minimumCabang as $cabangId => $min) {
            if (trim((string) $min) === '' || ! in_array((int) $cabangId, $lokasiValid, true)) {
                continue;
            }
            $min = (float) $min;
            $stok = Stock::withTrashed()->where('item_id', $item->id)->where('lokasi_id', (int) $cabangId)->first();

            if ($stok) {
                if ((float) $stok->qty_minimum !== $min) {
                    $stok->qty_minimum = $min;
                    $stok->updated_at = now();
                    $stok->save();
                }
            } elseif ($min > 0) {
                $stok = new Stock(['item_id' => $item->id, 'lokasi_id' => (int) $cabangId, 'qty' => 0, 'qty_minimum' => $min]);
                $stok->updated_at = now();
                $stok->save();
            }
        }
    }

    public function destroy(Item $bahanBaku, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.delete'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);

        try {
            $nama = $bahanBaku->nama_item;
            $cascadeService->deleteItemCascade($bahanBaku);

            return redirect()->route('master.bahan-baku.index')
                ->with('success', "Item <strong>{$nama}</strong> berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus item: ' . $e->getMessage());
        }
    }
}
