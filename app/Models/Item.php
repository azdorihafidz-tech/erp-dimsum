<?php

namespace App\Models;

use App\Enums\JenisItem;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'kode_item', 'nama_item', 'item_category_id', 'tipe', 'satuan',
        'harga_jual', 'harga_beli_terakhir', 'qty_minimum', 'is_active',
        'jenis', 'track_stok', 'punya_varian', 'stok_per_varian', 'foto',
    ];

    protected $fillable = [
        'kode_item',
        'nama_item',
        'item_category_id',
        'tipe',
        'satuan',
        'harga_jual',
        'harga_beli_terakhir',
        'qty_minimum',
        'deskripsi',
        'is_active',
        // Fase 5 — Modul Perlengkapan (Rule #66), murni additive: item
        // existing tetap jenis='bahan_baku'/track_stok=true (default kolom).
        'jenis',
        'track_stok',
        // Tahap 2 D'mentai — Fitur Varian (DB+Model saja, UI di Tahap 3).
        'punya_varian',
        'stok_per_varian',
        'foto',
    ];

    protected function casts(): array
    {
        return [
            'harga_jual' => 'decimal:2',
            'harga_beli_terakhir' => 'decimal:2',
            'qty_minimum' => 'decimal:3',
            'is_active' => 'boolean',
            'jenis' => JenisItem::class,
            'track_stok' => 'boolean',
            'punya_varian' => 'boolean',
            'stok_per_varian' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ===== Tahap 2 D'mentai — Fitur Varian (DB+Model saja, UI di Tahap 3) =====

    public function attributes()
    {
        return $this->hasMany(ItemAttribute::class)->orderBy('urutan');
    }

    public function variants()
    {
        return $this->hasMany(ItemVariant::class)->orderBy('urutan');
    }

    /**
     * Tahap 3 D'mentai — resep produksi item ini (nullable, item tanpa
     * breakdown bahan baku seperti minuman kemasan jadi/Item Tambahan tidak
     * wajib punya resep). Dipakai POS untuk cek+potong stok otomatis saat
     * checkout tanpa kasir pilih manual.
     */
    public function resep()
    {
        return $this->hasOne(ResepBumbu::class);
    }

    /**
     * Tahap 2.5 D'mentai — ketersediaan produk_jual/produk_tambahan per
     * outlet. Config eksplisit di `item_cabang` (create/edit form Produk
     * Jual). Lihat migration `create_item_cabang_table` untuk alasan
     * fallback ini.
     */
    public function itemCabang()
    {
        return $this->hasMany(ItemCabang::class);
    }

    /**
     * Item TANPA row sama sekali = dianggap aktif di semua cabang (backward
     * compat utk item lama yang belum pernah di-assign eksplisit). Row
     * eksplisit is_active=false = sengaja di-exclude dari outlet itu.
     */
    public function tersediaDiCabang(int $cabangId): bool
    {
        $row = $this->itemCabang->firstWhere('cabang_id', $cabangId);
        return $row === null || $row->is_active;
    }

    /** Harga jual efektif di 1 cabang — pakai override kalau ada & aktif, else harga_jual default. */
    public function hargaEfektifDiCabang(int $cabangId): float
    {
        $row = $this->itemCabang->firstWhere('cabang_id', $cabangId);
        if ($row && $row->is_active && $row->harga_override !== null) {
            return (float) $row->harga_override;
        }
        return (float) ($this->harga_jual ?? 0);
    }

    /**
     * Tahap 3 D'mentai — cek cepat "bisa dijual minimal 1 unit di cabang ini
     * sekarang?" dipakai grid POS untuk grey-out produk yang bahan bakunya
     * habis. Item dengan resep: SEMUA bahan komposisi harus tersedia
     * (>0). Tanpa resep: stok item itu sendiri yang dicek.
     *
     * Tahap 2.5: whitelist tipe yang dipotong stok langsung disamakan
     * dengan `produk_jual` (dulu `produk_jadi`) — 1:1 rename, sengaja
     * TIDAK menambah tambahan_gratis/produk_tambahan (behaviornya
     * dipertahankan sama seperti nilai lama 'lainnya': selalu sellable
     * tanpa cek stok, sesuai perilaku sebelum restructure ini).
     */
    public function bisaDijualDiCabang(int $cabangId): bool
    {
        $resep = $this->resep;

        if ($resep) {
            foreach ($resep->items as $resepItem) {
                if (! $resepItem->item_id) continue;
                $butuh = $resepItem->qty_per_unit_dalam_kg; // kebutuhan utk 1 unit
                if ($butuh <= 0) continue;
                if ($this->stokDiLokasiItem($resepItem->item_id, $cabangId) < $butuh) return false;
            }

            return true;
        }

        if (! in_array($this->tipe, ['bahan_baku', 'kemasan', 'produk_jual'], true)) return true;

        return $this->stokDiLokasi($cabangId) > 0;
    }

    /** Helper stok di lokasi untuk ITEM MANAPUN (bukan cuma $this) — dipakai bisaDijualDiCabang(). */
    private function stokDiLokasiItem(int $itemId, int $lokasiId): float
    {
        return Stock::where('item_id', $itemId)->where('lokasi_id', $lokasiId)->value('qty') ?? 0;
    }

    public function stokDiLokasi(int $lokasiId): float
    {
        return $this->stocks()->where('lokasi_id', $lokasiId)->value('qty') ?? 0;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBahanBaku($query)
    {
        return $query->where('tipe', 'bahan_baku');
    }

    public function scopeProdukJual($query)
    {
        return $query->where('tipe', 'produk_jual');
    }

    // ===== Fase 5 — Modul Perlengkapan (Rule #66), scope BARU, tidak
    // menyentuh scope existing di atas sama sekali =====

    public function scopeJenis($query, string $jenis)
    {
        return $query->where('jenis', $jenis);
    }

    public function scopePerlengkapan($query)
    {
        return $query->where('jenis', JenisItem::Perlengkapan->value);
    }
}
