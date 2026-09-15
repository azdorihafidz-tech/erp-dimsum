<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBumbu extends Model
{
    protected $table = 'resep_bumbu';

    protected $fillable = [
        'nama',
        'kode',
        'jenis_olahan_id',
        // Tahap 3 D'mentai — resep otomatis ketemu saat kasir klik produk di POS
        'item_id',
        'is_active',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items()
    {
        return $this->hasMany(ResepBumbuItem::class)->orderBy('urutan');
    }

    public function jenisOlahan()
    {
        return $this->belongsTo(JenisOlahan::class, 'jenis_olahan_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
