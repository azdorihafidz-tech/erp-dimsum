<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Potongan ganti rugi di satu slip gaji (Stok Opname Tahap D). */
class GantiRugiPotongan extends Model
{
    protected $table = 'ganti_rugi_potongan';

    protected $fillable = ['ganti_rugi_id', 'penggajian_id', 'periode', 'nominal', 'status', 'tk_gaji_id', 'tk_pulih_id'];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function gantiRugi()  { return $this->belongsTo(GantiRugi::class); }
    public function penggajian() { return $this->belongsTo(Penggajian::class)->withTrashed(); }
}
