<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stok Opname (RANCANGAN_STOK_OPNAME.md). Semua aturan ada di
 * App\Services\StokOpnameService — model ini hanya data & relasi.
 */
class StokOpname extends Model
{
    public const MENGHITUNG = 'menghitung';
    public const DIAJUKAN   = 'diajukan';
    public const DISETUJUI  = 'disetujui';
    public const DIBATALKAN = 'dibatalkan';

    /** Status yang masih "berjalan" (mengunci barang dari opname lain & Penyesuaian Stok). */
    public const BERJALAN = [self::MENGHITUNG, self::DIAJUKAN];

    protected $fillable = [
        'nomor', 'cabang_id', 'cakupan', 'cakupan_ids', 'hitung_buta', 'status', 'catatan',
        'dibuat_by', 'diajukan_at', 'diajukan_by', 'ditolak_at', 'ditolak_by', 'alasan_tolak',
        'disetujui_at', 'disetujui_by', 'tanggal_disetujui', 'dibatalkan_at', 'dibatalkan_by', 'alasan_batal',
        'ringkasan', 'akurasi',
    ];

    protected function casts(): array
    {
        return [
            'cakupan_ids'       => 'array',
            'ringkasan'         => 'array',
            'hitung_buta'       => 'boolean',
            'diajukan_at'       => 'datetime',
            'ditolak_at'        => 'datetime',
            'disetujui_at'      => 'datetime',
            'dibatalkan_at'     => 'datetime',
            'tanggal_disetujui' => 'date',
            'akurasi'           => 'decimal:2',
        ];
    }

    public function cabang()     { return $this->belongsTo(Cabang::class); }
    public function items()      { return $this->hasMany(StokOpnameItem::class); }
    public function pembuat()    { return $this->belongsTo(User::class, 'dibuat_by'); }
    public function pengaju()    { return $this->belongsTo(User::class, 'diajukan_by'); }
    public function penyetuju()  { return $this->belongsTo(User::class, 'disetujui_by'); }
    public function penolak()    { return $this->belongsTo(User::class, 'ditolak_by'); }
    public function pembatal()   { return $this->belongsTo(User::class, 'dibatalkan_by'); }
    public function penghitung() { return $this->belongsToMany(User::class, 'stok_opname_penghitung'); }

    public function berjalan(): bool
    {
        return in_array($this->status, self::BERJALAN, true);
    }

    /** Sedang di tahap hitung karena ditolak (bukan opname baru). */
    public function ditolak(): bool
    {
        return $this->status === self::MENGHITUNG && $this->ditolak_at !== null;
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::MENGHITUNG => $this->ditolak() ? 'Ditolak — perlu diperbaiki' : 'Sedang dihitung',
            self::DIAJUKAN   => 'Menunggu persetujuan',
            self::DISETUJUI  => 'Disetujui',
            self::DIBATALKAN => 'Dibatalkan',
            default          => (string) $this->status,
        };
    }

    public function warnaStatus(): string
    {
        return match ($this->status) {
            self::MENGHITUNG => $this->ditolak() ? 'danger' : 'info',
            self::DIAJUKAN   => 'warning',
            self::DISETUJUI  => 'success',
            default          => 'secondary',
        };
    }

    public function labelCakupan(): string
    {
        return match ($this->cakupan) {
            'kategori' => 'Per kategori',
            'barang'   => 'Barang tertentu',
            default    => 'Semua barang',
        };
    }
}
