<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat tindak lanjut stok rusak (Stok Opname Tahap C). */
class StokRusakTindakan extends Model
{
    protected $table = 'stok_rusak_tindakan';

    protected $fillable = [
        'stok_rusak_id', 'jenis', 'qty', 'nilai', 'uang', 'kas_id', 'supplier_id', 'nomor_po', 'nomor_ba',
        'foto_path', 'saksi', 'catatan', 'tanggal', 'user_id', 'transaksi_keuangan_id', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'qty'     => 'decimal:3',
            'nilai'   => 'decimal:2',
            'uang'    => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    public function stokRusak() { return $this->belongsTo(StokRusak::class); }
    public function kas()       { return $this->belongsTo(Kas::class); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function user()      { return $this->belongsTo(User::class); }
}
