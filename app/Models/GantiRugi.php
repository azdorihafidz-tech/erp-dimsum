<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ganti rugi karyawan atas barang Hilang di Stok Opname (Tahap D). */
class GantiRugi extends Model
{
    protected $table = 'ganti_rugi';

    protected $fillable = [
        'stok_opname_id', 'cabang_id', 'karyawan_id', 'nilai', 'alasan_nilai', 'cara', 'jumlah_cicilan', 'mulai_periode',
        'terbayar', 'status', 'kas_id', 'transaksi_keuangan_id', 'dibuat_by', 'dibatalkan_at', 'dibatalkan_by', 'alasan_batal',
    ];

    protected function casts(): array
    {
        return [
            'nilai'         => 'decimal:2',
            'terbayar'      => 'decimal:2',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function opname()   { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function cabang()   { return $this->belongsTo(Cabang::class); }
    public function karyawan() { return $this->belongsTo(Karyawan::class); }
    public function kas()      { return $this->belongsTo(Kas::class); }
    public function pembuat()  { return $this->belongsTo(User::class, 'dibuat_by'); }
    public function potongan() { return $this->hasMany(GantiRugiPotongan::class)->orderBy('periode'); }

    public function sisa(): float
    {
        return $this->status === 'batal' ? 0.0 : max(0, round((float) $this->nilai - (float) $this->terbayar, 2));
    }

    public function labelCara(): string
    {
        return $this->cara === 'tunai' ? 'Tunai' : 'Potong gaji ' . $this->jumlah_cicilan . '× mulai ' . $this->mulai_periode;
    }
}
