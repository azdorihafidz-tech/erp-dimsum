<?php

namespace App\Models;

use App\Enums\PenyebabSelisihStok;
use Illuminate\Database\Eloquent\Model;

/** Stok rusak ditahan per lokasi (dari Stok Opname), menunggu tindak lanjut. */
class StokRusak extends Model
{
    protected $table = 'stok_rusak';

    protected $fillable = [
        'cabang_id', 'item_id', 'stok_opname_rincian_id', 'penyebab', 'qty_awal', 'qty_sisa',
        'harga_pokok', 'tanggal', 'status',
    ];

    protected function casts(): array
    {
        return [
            'penyebab'    => PenyebabSelisihStok::class,
            'qty_awal'    => 'decimal:3',
            'qty_sisa'    => 'decimal:3',
            'harga_pokok' => 'decimal:4',
            'tanggal'     => 'date',
        ];
    }

    public function cabang()  { return $this->belongsTo(Cabang::class); }
    public function item()    { return $this->belongsTo(Item::class)->withTrashed(); }
    public function rincian() { return $this->belongsTo(StokOpnameRincian::class, 'stok_opname_rincian_id'); }
    public function tindakan() { return $this->hasMany(StokRusakTindakan::class)->orderBy('id'); }

    /** Opname asal (lewat rincian). */
    public function opname(): ?StokOpname
    {
        return $this->rincian?->opnameItem?->opname;
    }

    public function umurHari(): int
    {
        return (int) $this->tanggal?->diffInDays(today());
    }

    public function nilaiSisa(): float
    {
        return round((float) $this->qty_sisa * (float) $this->harga_pokok, 2);
    }
}
