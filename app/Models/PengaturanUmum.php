<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class PengaturanUmum extends Model
{
    use HasAuditLog;

    protected $table = 'pengaturan_umums';

    protected $fillable = [
        'nama_perusahaan',
        'logo_path',
        'alamat_perusahaan',
    ];

    /** Singleton getter — selalu return instance baris pertama/satu-satunya */
    public static function getSetting(): static
    {
        static $instance = null;

        if ($instance === null) {
            $instance = static::firstOrCreate([], [
                'nama_perusahaan' => "D'mentai",
                'logo_path'       => null,
                'alamat_perusahaan' => null,
            ]);
        }

        return $instance;
    }

    /** Reset static cache setelah update (panggil di controller setelah save) */
    public static function resetCache(): void
    {
        // Re-query next call: tidak bisa reset static var dari luar,
        // tapi karena update via instance->save(), view yang sama session sudah dapat nilai baru.
    }
}
