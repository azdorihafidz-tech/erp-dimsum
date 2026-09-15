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

        $data = $request->safe()->except('stok_awal');
        $data['kode_item'] = strtoupper($data['kode_item']);
        $data['is_active'] = $request->boolean('is_active', true);

        $item = Item::create($data);

        foreach ((array) $request->input('stok_awal', []) as $cabangId => $qty) {
            $qty = (float) $qty;
            if ($qty <= 0) continue;

            Stock::updateOrCreate(
                ['item_id' => $item->id, 'lokasi_id' => (int) $cabangId],
                ['qty' => $qty, 'qty_minimum' => $item->qty_minimum ?? 0]
            );
        }

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$item->nama_item} berhasil ditambahkan.");
    }

    public function edit(Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);

        $categories = ItemCategory::orderBy('nama_kategori')->get();

        return view('master.bahan-baku.edit', ['item' => $bahanBaku, 'categories' => $categories]);
    }

    public function update(BahanBakuRequest $request, Item $bahanBaku)
    {
        abort_unless(auth()->user()->can('master.bahan_baku.edit'), 403);
        abort_unless(in_array($bahanBaku->tipe, self::TIPE_SCOPE, true), 404);

        $data = $request->safe()->except('stok_awal');
        $data['kode_item'] = strtoupper($data['kode_item']);
        $data['is_active'] = $request->boolean('is_active', true);

        $bahanBaku->update($data);

        return redirect()->route('master.bahan-baku.index')
            ->with('success', "Bahan/Kemasan {$bahanBaku->nama_item} berhasil diperbarui.");
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
