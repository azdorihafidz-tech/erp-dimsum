<?php

namespace App\Models;

use App\Enums\PenyebabSelisihStok;
use Illuminate\Database\Eloquent\Model;

class StokOpnameRincian extends Model
{
    protected $table = 'stok_opname_rincian';

    protected $fillable = [
        'stok_opname_item_id', 'penyebab', 'qty', 'foto_path', 'catatan', 'nilai',
        'transaksi_keuangan_id', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'penyebab' => PenyebabSelisihStok::class,
            'qty'      => 'decimal:3',
            'nilai'    => 'decimal:2',
        ];
    }

    public function opnameItem() { return $this->belongsTo(StokOpnameItem::class, 'stok_opname_item_id'); }
    public function transaksi()  { return $this->belongsTo(TransaksiKeuangan::class, 'transaksi_keuangan_id'); }
}
