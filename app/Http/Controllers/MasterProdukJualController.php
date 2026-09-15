<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProdukJualRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemCabang;
use App\Models\ItemCategory;
use App\Models\ItemVariant;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tahap 2.5 D'mentai — menu "Produk Jual": form LENGKAP (foto + resep +
 * varian + ketersediaan outlet) untuk items bertipe produk_jual/produk_tambahan.
 * Tetap 1 tabel `items` (lihat CLAUDE.md Tahap 2.5) — controller ini cuma
 * beda filter+form dari Master Bahan Baku.
 */
class MasterProdukJualController extends Controller
{
    private const TIPE_SCOPE = ['produk_jual', 'produk_tambahan'];
    private const MAX_DIMENSI = 800;

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.view'), 403);

        $query = Item::with('category')->whereIn('tipe', self::TIPE_SCOPE)->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_item', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_item', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('kategori')) {
            $query->where('item_category_id', $request->kategori);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        $items      = $query->paginate(20)->withQueryString();
        $categories = ItemCategory::orderBy('nama_kategori')->get();

        return view('master.produk-jual.index', compact('items', 'categories'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('master.produk_jual.create'), 403);

        $categories  = ItemCategory::orderBy('nama_kategori')->get();
        $cabangList  = Cabang::aktif()->orderBy('nama_cabang')->get();
        $bahanOptions = Item::whereIn('tipe', ['bahan_baku', 'kemasan', 'tambahan_gratis'])
            ->aktif()->orderBy('nama_item')->get();

        return view('master.produk-jual.create', compact('categories', 'cabangList', 'bahanOptions'));
    }

    public function store(ProdukJualRequest $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.create'), 403);

        $data = $request->safe()->only([
            'kode_item', 'nama_item', 'item_category_id', 'tipe', 'satuan',
            'harga_jual', 'deskripsi',
        ]);
        $data['kode_item']       = strtoupper($data['kode_item']);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['punya_varian']    = $request->boolean('punya_varian') && filled($request->input('atribut'));
        $data['stok_per_varian'] = $request->boolean('stok_per_varian');

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->simpanFoto($request->file('foto'));
        }

        $item = Item::create($data);

        $this->syncResep($item, (array) $request->input('resep', []));
        if ($data['punya_varian']) {
            $this->syncAttributesAndVariants($item, (array) $request->input('atribut', []), (array) $request->input('harga_override', []));
        }
        $this->syncCabang($item, array_map('intval', (array) $request->input('cabang_aktif', [])), (array) $request->input('cabang_harga', []));

        return redirect()->route('master.produk-jual.index')
            ->with('success', "Produk {$item->nama_item} berhasil ditambahkan.");
    }

    public function edit(Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);

        $produkJual->load([
            'resep.items.item', 'resep.items.resepBumbuRef',
            'attributes.values',
            'variants' => fn ($q) => $q->orderBy('urutan'),
            'variants.attributeValues.attribute',
            'itemCabang',
        ]);

        $categories   = ItemCategory::orderBy('nama_kategori')->get();
        $cabangList   = Cabang::aktif()->orderBy('nama_cabang')->get();
        $bahanOptions = Item::whereIn('tipe', ['bahan_baku', 'kemasan', 'tambahan_gratis'])
            ->aktif()->orderBy('nama_item')->get();

        return view('master.produk-jual.edit', [
            'item' => $produkJual, 'categories' => $categories,
            'cabangList' => $cabangList, 'bahanOptions' => $bahanOptions,
        ]);
    }

    public function update(ProdukJualRequest $request, Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);

        $data = $request->safe()->only([
            'kode_item', 'nama_item', 'item_category_id', 'tipe', 'satuan',
            'harga_jual', 'deskripsi',
        ]);
        $data['kode_item']       = strtoupper($data['kode_item']);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['stok_per_varian'] = $request->boolean('stok_per_varian');

        $punyaVarianBaru = $request->boolean('punya_varian') && filled($request->input('atribut'));
        $data['punya_varian'] = $punyaVarianBaru;

        if ($request->boolean('hapus_foto') && $produkJual->foto) {
            Storage::disk('public')->delete($produkJual->foto);
            $data['foto'] = null;
        }
        if ($request->hasFile('foto')) {
            if ($produkJual->foto) {
                Storage::disk('public')->delete($produkJual->foto);
            }
            $data['foto'] = $this->simpanFoto($request->file('foto'));
        }

        $produkJual->update($data);

        $this->syncResep($produkJual, (array) $request->input('resep', []));
        // Toggle OFF varian: SENGAJA tidak menghapus attribute/variant lama
        // (bisa direferensikan order_items.item_variant_id historis) — cukup
        // matikan flag, POS otomatis berhenti menampilkan modal varian.
        if ($punyaVarianBaru) {
            $this->syncAttributesAndVariants($produkJual, (array) $request->input('atribut', []), (array) $request->input('harga_override', []));
        }
        $this->syncCabang($produkJual, array_map('intval', (array) $request->input('cabang_aktif', [])), (array) $request->input('cabang_harga', []));

        return redirect()->route('master.produk-jual.index')
            ->with('success', "Produk {$produkJual->nama_item} berhasil diperbarui.");
    }

    public function destroy(Item $produkJual, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('master.produk_jual.delete'), 403);
        abort_unless(in_array($produkJual->tipe, self::TIPE_SCOPE, true), 404);

        try {
            $nama = $produkJual->nama_item;
            if ($produkJual->foto) {
                Storage::disk('public')->delete($produkJual->foto);
            }
            $cascadeService->deleteItemCascade($produkJual);

            return redirect()->route('master.produk-jual.index')
                ->with('success', "Produk <strong>{$nama}</strong> berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    /**
     * AJAX helper (dipakai kalkulator resep di form) — hitung breakdown
     * bahan untuk N pcs produksi. Murni preview, tidak menyentuh DB.
     * Baris linked (Import Bumbu Pusat) ditampilkan SEBAGAI 1 baris
     * teragregasi (bukan pecah per bahan dalam bumbu) — dihitung LIVE dari
     * komposisi bumbu saat ini, jadi otomatis akurat kalau bumbu diedit.
     */
    public function kalkulatorResep(Request $request, Item $produkJual)
    {
        abort_unless(auth()->user()->can('master.produk_jual.view'), 403);

        $jumlah = max(1, (int) $request->input('jumlah', 1));
        $produkJual->load('resep.items.item', 'resep.items.resepBumbuRef', 'resep.items.resepBumbuRef.items.item');

        $breakdown = [];
        $totalHpp = 0.0;

        foreach ($produkJual->resep?->items ?? [] as $ri) {
            if ($ri->isLinked()) {
                if (! $ri->resepBumbuRef) continue;
                $subtotalBumbu = 0.0;
                foreach ($ri->resepBumbuRef->items as $inner) {
                    if (! $inner->item || $inner->mode_harga !== 'pakai_master') continue;
                    $qtyInner = $inner->qty_per_unit_dalam_kg * $ri->qty_per_unit * $jumlah;
                    $subtotalBumbu += $qtyInner * (float) ($inner->item->harga_beli_terakhir ?? 0);
                }
                $totalHpp += $subtotalBumbu;
                $breakdown[] = [
                    'nama'     => '🧂 ' . $ri->resepBumbuRef->nama . ' (Bumbu Pusat)',
                    'qty'      => round($ri->qty_per_unit * $jumlah, 3),
                    'satuan'   => 'porsi',
                    'hpp_satuan' => null,
                    'subtotal' => $subtotalBumbu,
                    'linked'   => true,
                ];
                continue;
            }

            if (! $ri->item) continue;
            $qtyDibutuhkan = $ri->qty_per_unit * $jumlah;
            $hppSatuan = (float) ($ri->item->harga_beli_terakhir ?? 0);
            $subtotal = $qtyDibutuhkan * $hppSatuan;
            $totalHpp += $subtotal;

            $breakdown[] = [
                'nama'  => $ri->item->nama_item,
                'qty'   => round($qtyDibutuhkan, 3),
                'satuan'=> $ri->satuan,
                'hpp_satuan' => $hppSatuan,
                'subtotal' => $subtotal,
                'linked' => false,
            ];
        }

        return response()->json(['jumlah_produksi' => $jumlah, 'breakdown' => $breakdown, 'total_hpp' => $totalHpp]);
    }

    /**
     * AJAX picker "Import dari Bumbu Pusat" — list Master Bumbu aktif
     * (ResepBumbu tanpa item_id) utk dipilih di form Produk Jual. Reuse
     * permission master.produk_jual.edit (bukan bikin permission baru,
     * keputusan Owner 2026-09-15) -- siapapun yang boleh edit produk boleh
     * lihat & pakai daftar bumbu ini.
     */
    public function listBumbuPusat(Request $request)
    {
        abort_unless(auth()->user()->can('master.produk_jual.edit'), 403);

        $search = $request->input('search', '');

        $bumbus = ResepBumbu::whereNull('item_id')
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->withCount('items')
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'kode']);

        return response()->json([
            'data' => $bumbus->map(fn ($b) => [
                'id' => $b->id, 'nama' => $b->nama, 'kode' => $b->kode, 'jumlah_bahan' => $b->items_count,
            ]),
        ]);
    }

    // ===== Helpers privat =====

    private function simpanFoto($file): string
    {
        $mime = $file->getMimeType();
        $ext  = $mime === 'image/png' ? 'png' : 'jpg';
        $filename = 'produk/' . uniqid('produk_', true) . '.' . $ext;

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png'  => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : null,
            default      => null,
        };

        if (! $src) {
            // GD gagal decode (format tidak didukung server ini) — fallback simpan apa adanya
            return $file->store('produk', 'public');
        }

        $width  = imagesx($src);
        $height = imagesy($src);

        if ($width > self::MAX_DIMENSI || $height > self::MAX_DIMENSI) {
            $ratio  = min(self::MAX_DIMENSI / $width, self::MAX_DIMENSI / $height);
            $newW   = (int) round($width * $ratio);
            $newH   = (int) round($height * $ratio);
            $dst    = imagecreatetruecolor($newW, $newH);

            if ($mime === 'image/png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($src);
            $src = $dst;
        }

        Storage::disk('public')->makeDirectory('produk');
        $fullPath = Storage::disk('public')->path($filename);

        if ($ext === 'png') {
            imagepng($src, $fullPath);
        } else {
            imagejpeg($src, $fullPath, 85);
        }
        imagedestroy($src);

        return $filename;
    }

    /**
     * Wipe & rebuild resep (ResepBumbuItem plain table, tidak ada FK order
     * yang bergantung ke baris resep). Fitur "Import dari Bumbu Pusat"
     * (2026-09-17) — 1 baris resep sekarang bisa item_id LANGSUNG (bahan
     * manual) ATAU resep_bumbu_ref_id (link ke Master Bumbu Pusat).
     */
    private function syncResep(Item $item, array $resepInput): void
    {
        $resep = $item->resep()->first();

        $resepInput = array_values(array_filter($resepInput, fn ($r) => !empty($r['item_id']) || !empty($r['resep_bumbu_ref_id'])));

        if (empty($resepInput)) {
            if ($resep) {
                $resep->items()->delete();
                $resep->delete();
            }
            return;
        }

        if (! $resep) {
            $resep = ResepBumbu::create([
                'nama'         => $item->nama_item,
                'kode'         => 'RESEP-' . $item->kode_item,
                'item_id'      => $item->id,
                'is_active'    => true,
                'dibuat_oleh'  => auth()->id(),
            ]);
        }

        // Master Bumbu Pusat valid utk di-link: item_id NULL (murni bumbu,
        // bukan resep produk lain) DAN bukan resep milik item ini sendiri
        // (self-reference, walau secara struktural mustahil krn resep item
        // ini selalu punya item_id != null -- tetap dicek eksplisit demi
        // defense-in-depth).
        $bumbuValidIds = ResepBumbu::whereNull('item_id')
            ->where('id', '!=', $resep->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $resep->items()->delete();
        foreach ($resepInput as $i => $row) {
            $resepBumbuRefId = !empty($row['resep_bumbu_ref_id']) ? (int) $row['resep_bumbu_ref_id'] : null;

            if ($resepBumbuRefId && !in_array($resepBumbuRefId, $bumbuValidIds, true)) {
                // Bumbu tidak valid (nonaktif/dihapus/sudah bukan master bumbu murni
                // sejak dipilih di form) -- skip baris ini daripada simpan data korup.
                continue;
            }

            ResepBumbuItem::create([
                'resep_bumbu_id'      => $resep->id,
                'item_id'             => $resepBumbuRefId ? null : $row['item_id'],
                'resep_bumbu_ref_id'  => $resepBumbuRefId,
                'qty_per_unit'        => $row['qty_per_unit'],
                'satuan'              => $resepBumbuRefId ? 'porsi' : $row['satuan'],
                'is_wajib'            => filter_var($row['is_wajib'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'mode_harga'          => $row['mode_harga'] ?? 'gratis',
                'urutan'              => $i,
            ]);
        }
    }

    /**
     * Sync struktur atribut+nilai+varian TANPA merusak riwayat order lama:
     *  - Attribute/value dicocokkan by nama (updateOrCreate) -> id tetap
     *    sama kalau nama tidak berubah.
     *  - Kombinasi varian yang SAMA (signature = set attribute_value_id)
     *    dipertahankan (id, harga_override existing tidak hilang kecuali
     *    di-override eksplisit dari form).
     *  - Kombinasi yang sudah tidak match struktur baru -> SOFT DELETE
     *    (bukan hard delete) supaya order_items.item_variant_id historis
     *    tetap valid.
     */
    private function syncAttributesAndVariants(Item $item, array $atributInput, array $hargaOverrideInput): void
    {
        $atributInput = array_values(array_filter($atributInput, fn ($a) => filled($a['nama'] ?? null) && filled($a['nilai'] ?? null)));

        // Snapshot variant lama (signature -> variant) SEBELUM attribute/value diubah.
        $variantLama = $item->variants()->with('attributeValues')->get()
            ->mapWithKeys(function (ItemVariant $v) {
                $sig = $v->attributeValues->pluck('id')->sort()->implode(',');
                return [$sig => $v];
            });

        $attributeIdsAktif = [];
        $valueGroupsByAttribute = []; // [attributeId => [valueId, ...]] urut sesuai input

        foreach ($atributInput as $ai => $row) {
            // updateOrCreate() tidak bisa restore soft-delete (deleted_at
            // bukan fillable) -> restore manual via set atribut langsung.
            $attribute = ItemAttribute::withTrashed()
                ->firstOrNew(['item_id' => $item->id, 'nama' => trim($row['nama'])]);
            $attribute->urutan = $ai;
            if ($attribute->trashed()) $attribute->deleted_at = null;
            $attribute->save();
            $attributeIdsAktif[] = $attribute->id;

            $nilaiList = array_values(array_filter(array_map('trim', explode(',', $row['nilai']))));
            $valueIds = [];
            foreach ($nilaiList as $vi => $nilai) {
                $value = ItemAttributeValue::withTrashed()
                    ->firstOrNew(['item_attribute_id' => $attribute->id, 'nilai' => $nilai]);
                $value->urutan = $vi;
                if ($value->trashed()) $value->deleted_at = null;
                $value->save();
                $valueIds[] = $value->id;
            }
            $valueGroupsByAttribute[] = $valueIds;

            // Value yang tidak lagi disubmit -> soft delete (attribute masih aktif)
            ItemAttributeValue::where('item_attribute_id', $attribute->id)
                ->whereNotIn('id', $valueIds)->delete();
        }

        // Attribute yang tidak lagi disubmit -> soft delete
        ItemAttribute::where('item_id', $item->id)
            ->whereNotIn('id', $attributeIdsAktif)->delete();

        // Cartesian product dari value groups (urut sesuai urutan attribute/value submit)
        $combos = [[]];
        foreach ($valueGroupsByAttribute as $valueIds) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($valueIds as $valueId) {
                    $next[] = [...$combo, $valueId];
                }
            }
            $combos = $next;
        }

        $signatureBaru = [];
        foreach ($combos as $i => $combo) {
            $sig = collect($combo)->sort()->implode(',');
            $signatureBaru[] = $sig;

            if (isset($variantLama[$sig])) {
                $variant = $variantLama[$sig];
                if (array_key_exists($i, $hargaOverrideInput) && $hargaOverrideInput[$i] !== '') {
                    $variant->harga_override = $hargaOverrideInput[$i];
                }
                $variant->is_active = true;
                $variant->urutan = $i;
                $variant->save();
                continue;
            }

            $variant = ItemVariant::create([
                'item_id'        => $item->id,
                'harga_override' => ($hargaOverrideInput[$i] ?? '') !== '' ? $hargaOverrideInput[$i] : null,
                'is_active'      => true,
                'urutan'         => $i,
            ]);
            $variant->attributeValues()->sync($combo);
        }

        // Kombinasi lama yang tidak match struktur baru -> soft delete (preserve histori order).
        // PENTING: PHP auto-cast array key numerik-string ("1") jadi integer di
        // mapWithKeys() saat membangun $variantLama -> $sig di sini bisa berupa
        // int walau $signatureBaru isinya string murni (implode selalu string).
        // WAJIB cast (string) sebelum in_array(strict) supaya produk ber-1-atribut
        // (signature "1"/"2"/dst, bukan gabungan "1,2") tidak salah dianggap
        // "tidak match" lalu ke-soft-delete semua padahal seharusnya dipertahankan.
        foreach ($variantLama as $sig => $variant) {
            if (! in_array((string) $sig, $signatureBaru, true)) {
                $variant->delete();
            }
        }
    }

    private function syncCabang(Item $item, array $cabangAktifIds, array $cabangHarga): void
    {
        foreach (Cabang::aktif()->pluck('id') as $cabangId) {
            ItemCabang::updateOrCreate(
                ['item_id' => $item->id, 'cabang_id' => $cabangId],
                [
                    'is_active'      => in_array($cabangId, $cabangAktifIds, true),
                    'harga_override' => ($cabangHarga[$cabangId] ?? '') !== '' ? $cabangHarga[$cabangId] : null,
                ]
            );
        }
    }
}
