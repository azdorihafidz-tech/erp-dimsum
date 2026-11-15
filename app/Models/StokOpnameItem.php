<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokOpnameItem extends Model
{
    protected $fillable = [
        'stok_opname_id', 'item_id', 'qty_sistem', 'qty_fisik', 'dihitung_at', 'dihitung_by',
        'hitung_ulang', 'dikeluarkan', 'alasan_dikeluarkan', 'harga_pokok', 'nilai_selisih',
    ];

    protected function casts(): array
    {
        return [
            'qty_sistem'    => 'decimal:3',
            'qty_fisik'     => 'decimal:3',
            'dihitung_at'   => 'datetime',
            'hitung_ulang'  => 'boolean',
            'dikeluarkan'   => 'boolean',
            'harga_pokok'   => 'decimal:4',
            'nilai_selisih' => 'decimal:2',
        ];
    }

    public function opname()     { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function item()       { return $this->belongsTo(Item::class)->withTrashed(); }
    public function penghitung() { return $this->belongsTo(User::class, 'dihitung_by'); }
    public function rincian()    { return $this->hasMany(StokOpnameRincian::class); }

    /** Sudah ada jumlah fisik yang berlaku (bukan sedang diminta hitung ulang). */
    public function sudahDihitung(): bool
    {
        return $this->qty_fisik !== null && ! $this->hitung_ulang;
    }

    /** fisik − sistem (null kalau belum dihitung). */
    public function selisih(): ?float
    {
        if ($this->qty_fisik === null || $this->qty_sistem === null) {
            return null;
        }

        return round((float) $this->qty_fisik - (float) $this->qty_sistem, 3);
    }
}
