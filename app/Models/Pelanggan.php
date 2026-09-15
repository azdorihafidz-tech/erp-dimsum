<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pelanggan extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'kode_pelanggan', 'nama_pelanggan', 'telepon',
        'email', 'alamat', 'kota', 'is_active',
    ];

    protected $fillable = [
        'kode_pelanggan',
        'nama_pelanggan',
        'telepon',
        'email',
        'alamat',
        'kota',
        'catatan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeAktif($q)
    {
        return $q->where('is_active', true);
    }
}
