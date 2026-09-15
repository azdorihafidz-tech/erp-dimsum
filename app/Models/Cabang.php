<?php

namespace App\Models;

use App\Enums\TipeCabang;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'nama_cabang', 'kode_cabang', 'alamat', 'tipe',
        'kepala_cabang_id', 'latitude', 'longitude',
        'radius_absen_meter', 'jam_masuk', 'is_active',
        'antrian_produksi_aktif', 'running_text_aktif', 'footer_struk',
        'izinkan_sembunyi_harga_struk',
    ];

    protected $fillable = [
        'nama_cabang',
        'kode_cabang',
        'alamat',
        'telepon',
        'kepala_cabang_id',
        'tipe',
        'is_active',
        'latitude',
        'longitude',
        'radius_absen_meter',
        'jam_masuk',
        'antrian_produksi_aktif',
        'running_text',
        'running_text_aktif',
        'footer_struk',
        'izinkan_sembunyi_harga_struk',
        // Tahap 3 D'mentai — config POS per outlet (CLAUDE.md 1.4: jangan hardcode)
        'dine_in_aktif',
        'takeaway_aktif',
        'frozen_aktif',
        'nomor_meja_aktif',
        'take_away_fee',
        'service_charge_persen',
    ];

    protected function casts(): array
    {
        return [
            'tipe'                   => TipeCabang::class,
            'is_active'              => 'boolean',
            'latitude'               => 'decimal:8',
            'longitude'              => 'decimal:8',
            'radius_absen_meter'     => 'integer',
            'antrian_produksi_aktif' => 'boolean',
            'running_text_aktif'     => 'boolean',
            'izinkan_sembunyi_harga_struk' => 'boolean',
            'dine_in_aktif'          => 'boolean',
            'takeaway_aktif'         => 'boolean',
            'frozen_aktif'           => 'boolean',
            'nomor_meja_aktif'       => 'boolean',
            'take_away_fee'          => 'decimal:2',
            'service_charge_persen'  => 'decimal:2',
        ];
    }

    /** Tahap 3 D'mentai — cek apakah tipe transaksi ini aktif utk cabang ini. */
    public function tipeTransaksiAktif(\App\Enums\TipeTransaksi $tipe): bool
    {
        return (bool) $this->{$tipe->kolomAktif()};
    }

    public function kepalaCabang()
    {
        return $this->belongsTo(User::class, 'kepala_cabang_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'cabang_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'lokasi_id');
    }

    public function karyawans()
    {
        return $this->hasMany(Karyawan::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'lokasi_id');
    }

    public function absenDevices()
    {
        return $this->hasMany(AbsenDevice::class);
    }

    public function faceAttendances()
    {
        return $this->hasMany(FaceAttendance::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class)->orderBy('id');
    }

    /** Hitung jarak (meter) dari koordinat cabang ke titik lain menggunakan Haversine */
    public function hitungJarak(float $lat, float $lng): float
    {
        if (!$this->latitude || !$this->longitude) {
            return 0;
        }

        $earthRadius = 6371000; // meter
        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function isLokasiValid(float $lat, float $lng): bool
    {
        if (!$this->latitude || !$this->longitude) {
            return true; // GPS belum diset, izinkan
        }
        return $this->hitungJarak($lat, $lng) <= $this->radius_absen_meter;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCabangSaja($query)
    {
        return $query->where('tipe', TipeCabang::Cabang);
    }

    public function scopeGudangPusat($query)
    {
        return $query->where('tipe', TipeCabang::GudangPusat);
    }
}
