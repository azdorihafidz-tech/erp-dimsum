<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBumbuItem extends Model
{
    protected $fillable = [
        'resep_bumbu_id',
        'item_id',
        // Fitur "Import dari Bumbu Pusat" (2026-09-17) — mutually exclusive
        // dengan item_id: baris ini item_id ATAU resep_bumbu_ref_id, tidak
        // dua-duanya/kosong dua-duanya (divalidasi di controller, bukan DB
        // constraint). Lihat isLinked().
        'resep_bumbu_ref_id',
        'qty_per_unit',
        'satuan',
        'is_wajib',
        'mode_harga',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:3',
            'is_wajib'   => 'boolean',
        ];
    }

    public function resepBumbu()
    {
        return $this->belongsTo(ResepBumbu::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** Master Bumbu Pusat yang di-link (Fitur Import Bumbu Pusat, 2026-09-17). */
    public function resepBumbuRef()
    {
        return $this->belongsTo(ResepBumbu::class, 'resep_bumbu_ref_id');
    }

    public function isLinked(): bool
    {
        return $this->resep_bumbu_ref_id !== null;
    }

    /**
     * Expand baris linked jadi daftar kebutuhan bahan MENTAH (item_id
     * langsung), sudah di-skala oleh qty_per_unit_dalam_kg baris ini
     * (multiplier "berapa porsi bumbu per 1 unit produk"). Baris non-linked
     * (item_id langsung) kembalikan dirinya sendiri sebagai 1 elemen.
     * Anti cyclic-reference BY CONSTRUCTION: item milik Master Bumbu Pusat
     * (resepBumbuRef->items) TIDAK PERNAH linked lagi (lihat migration +
     * MasterResepBumbuController::storeItem yang tidak terima resep_bumbu_ref_id),
     * jadi expand ini SELALU cuma 1 level, tidak perlu rekursi/deteksi cycle.
     *
     * @return array<int, array{item_id:int, butuh_kg:float}>
     */
    public function expandKeBahanMentah(): array
    {
        if (! $this->isLinked()) {
            return $this->item_id ? [['item_id' => $this->item_id, 'butuh_kg' => $this->qty_per_unit_dalam_kg]] : [];
        }

        $bumbu = $this->resepBumbuRef ?? $this->resepBumbuRef()->first();
        if (! $bumbu) return [];

        $hasil = [];
        foreach ($bumbu->items as $innerItem) {
            if (! $innerItem->item_id) continue; // safety: skip kalau ada data korup
            $hasil[] = [
                'item_id'  => $innerItem->item_id,
                'butuh_kg' => $innerItem->qty_per_unit_dalam_kg * $this->qty_per_unit_dalam_kg,
            ];
        }
        return $hasil;
    }

    /**
     * Konversi qty_per_unit ke kg sesuai satuan takaran resep (g/gram/ons/ml).
     * Satu-satunya sumber konversi — dipakai juga oleh endpoint AJAX POS
     * (PenjualanController::resepBumbuItems) supaya tidak ada logic ganda.
     */
    public function getQtyPerUnitDalamKgAttribute(): float
    {
        return match (strtolower((string) $this->satuan)) {
            'g', 'gram', 'ml' => (float) $this->qty_per_unit / 1000,
            'ons'             => (float) $this->qty_per_unit / 10,
            default           => (float) $this->qty_per_unit, // 'kg' atau satuan tak dikenal
        };
    }

    /**
     * Preview harga (display-only, TIDAK disimpan ke DB) — total biaya bahan
     * ini per 1 unit produksi kalau mode_harga = pakai_master. Gratis = 0.
     */
    public function getTotalHargaMasterAttribute(): float
    {
        if ($this->mode_harga !== 'pakai_master') {
            return 0.0;
        }
        return round($this->qty_per_unit_dalam_kg * (float) ($this->item?->harga_jual ?? 0), 2);
    }
}
