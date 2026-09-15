<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('item.view'), 403);

        $query = Item::with('category')->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%'.$request->search.'%')
                  ->orWhere('kode_item', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }
        // Fase 5 — Modul Perlengkapan (Rule #66): filter jenis BARU,
        // independen dari filter tipe existing di atas (tidak disentuh).
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis', 'produk_jual' => 'Produk Jual', 'produk_tambahan' => 'Produk Tambahan'];

        $stats = [
            'total'       => Item::count(),
            'bahan_baku'  => Item::where('tipe', 'bahan_baku')->count(),
            'produk_jadi' => Item::where('tipe', 'produk_jual')->count(),
            'nonaktif'    => Item::where('is_active', false)->count(),
        ];

        return view('item.index', compact('items', 'categories', 'tipes', 'stats'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('item.create'), 403);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis', 'produk_jual' => 'Produk Jual', 'produk_tambahan' => 'Produk Tambahan'];
        return view('item.create', compact('categories', 'tipes'));
    }

    public function store(ItemRequest $request)
    {
        abort_unless(auth()->user()->can('item.create'), 403);

        $data = $request->validated();
        $data['kode_item']  = strtoupper($data['kode_item']);
        $data['is_active']  = $request->boolean('is_active', true);
        // Fase 5 — Modul Perlengkapan (Rule #66): track_stok toggle, pola
        // sama seperti is_active di atas (checkbox absen = false).
        $data['track_stok'] = $request->boolean('track_stok', true);

        Item::create($data);

        return redirect()->route('item.index')->with('success', "Item {$data['nama_item']} berhasil ditambahkan.");
    }

    public function show(Item $item)
    {
        abort_unless(auth()->user()->can('item.view'), 403);

        $item->load(['category', 'stocks.lokasi']);

        $recentMovements = StockMovement::with(['lokasiAsal', 'lokasiTujuan', 'user'])
            ->where('item_id', $item->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('item.show', compact('item', 'recentMovements'));
    }

    public function edit(Item $item)
    {
        abort_unless(auth()->user()->can('item.edit'), 403);

        $categories = ItemCategory::orderBy('nama_kategori')->get();
        $tipes      = ['bahan_baku' => 'Bahan Baku', 'kemasan' => 'Kemasan', 'tambahan_gratis' => 'Tambahan Gratis', 'produk_jual' => 'Produk Jual', 'produk_tambahan' => 'Produk Tambahan'];
        // Bug pre-existing ditemukan saat testing Tahap 2.5 (bukan disebabkan
        // restructure ini): view butuh $authUser (danger zone) tapi tidak
        // pernah di-compact -> selalu 500 di halaman ini. Fix minimal.
        $authUser = auth()->user();
        return view('item.edit', compact('item', 'categories', 'tipes', 'authUser'));
    }

    public function update(ItemRequest $request, Item $item)
    {
        abort_unless(auth()->user()->can('item.edit'), 403);

        $data = $request->validated();
        $data['kode_item'] = strtoupper($data['kode_item']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['track_stok'] = $request->boolean('track_stok', true);

        $item->update($data);

        return redirect()->route('item.index')->with('success', "Item {$item->nama_item} berhasil diperbarui.");
    }

    public function destroy(Item $item, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('item.delete'), 403);

        try {
            $nama    = $item->nama_item;
            $deleted = $cascadeService->deleteItemCascade($item);

            $labels    = ['order_item' => 'Item Order', 'purchase_order_item' => 'Item PO', 'stok' => 'Stok', 'item' => 'Barang'];
            $ringkasan = collect($deleted)->map(fn($c, $k) => "{$c} " . ($labels[$k] ?? $k))->filter()->join(', ');

            return redirect()->route('item.index')
                ->with('success', "Item <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus item: " . $e->getMessage());
        }
    }

    // ===== KATEGORI (inline) =====

    public function storeKategori(Request $request)
    {
        abort_unless(auth()->user()->can('item.create'), 403);

        $request->validate([
            'kode_kategori' => 'required|string|max:20|unique:item_categories,kode_kategori',
            'nama_kategori' => 'required|string|max:100',
        ]);

        ItemCategory::create([
            'kode_kategori' => strtoupper($request->kode_kategori),
            'nama_kategori' => $request->nama_kategori,
        ]);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }
}
