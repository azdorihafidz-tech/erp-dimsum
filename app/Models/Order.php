<?php

namespace App\Models;

use App\Enums\StatusOrder;
use App\Enums\StatusProduksi;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeTransaksi;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id',
        'nomor_order',
        'tanggal_order',
        'tipe_order',
        // Tahap 3 D'mentai — POS
        'tipe_transaksi',
        'nomor_meja',
        'tanggal_expired_frozen',
        'pelanggan_id',
        'nama_pelanggan',
        'telepon_pelanggan',
        'total_harga',
        'diskon',
        'service_charge',
        'take_away_fee',
        'total_bayar',
        'jumlah_bayar',
        'kembalian',
        'tipe_pembayaran',
        'kas_id',
        'status',
        'catatan',
        'kasir_id',
        'bukti_pembayaran',
        // Antrian produksi
        'nomor_antrian',
        'berat_daging_kg',
        'status_produksi',
        'tampil_di_antrian',
        'lokasi_rak',
        'dikerjakan_oleh_id',
        'waktu_mulai_kerja',
        'waktu_selesai_kerja',
        'waktu_disimpan',
        'waktu_diambil',
        'catatan_produksi',
        // Order pengganti & pembatalan
        'parent_order_id',
        'alasan_pembatalan_kategori',
        'alasan_pembatalan_detail',
    ];

    protected function casts(): array
    {
        return [
            'status'              => StatusOrder::class,
            'tipe_order'          => TipeOrder::class,
            'tipe_transaksi'      => TipeTransaksi::class,
            'tipe_pembayaran'     => TipePembayaran::class,
            'status_produksi'     => StatusProduksi::class,
            'tanggal_order'       => 'date',
            'tanggal_expired_frozen' => 'date',
            'total_harga'         => 'decimal:2',
            'diskon'              => 'decimal:2',
            'service_charge'      => 'decimal:2',
            'take_away_fee'       => 'decimal:2',
            'total_bayar'         => 'decimal:2',
            'jumlah_bayar'        => 'decimal:2',
            'kembalian'           => 'decimal:2',
            'berat_daging_kg'     => 'decimal:2',
            'tampil_di_antrian'   => 'boolean',
            'waktu_mulai_kerja'   => 'datetime',
            'waktu_selesai_kerja' => 'datetime',
            'waktu_disimpan'      => 'datetime',
            'waktu_diambil'       => 'datetime',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    /** Tahap 3 D'mentai — Split Payment: rincian metode bayar order ini. */
    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function dikerjakanOleh()
    {
        return $this->belongsTo(Karyawan::class, 'dikerjakan_oleh_id');
    }

    /** Order asli yang digantikan oleh order ini (kalau order ini pengganti) */
    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /** Order pengganti yang dibuat untuk menggantikan order ini */
    public function childOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    /** Generate nomor antrian berikutnya untuk cabang & tanggal tertentu */
    public static function generateNomorAntrian(int $cabangId, $tanggal): int
    {
        $max = self::withTrashed()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $tanggal)
            ->whereNotNull('nomor_antrian')
            ->max('nomor_antrian');

        return ($max ?? 0) + 1;
    }

    public function mulaiKerja(int $karyawanId): void
    {
        $this->update([
            'status_produksi'   => StatusProduksi::Dikerjakan,
            'dikerjakan_oleh_id'=> $karyawanId,
            'waktu_mulai_kerja' => now(),
        ]);
    }

    public function tandaiSelesai(): void
    {
        $this->update([
            'status_produksi'     => StatusProduksi::Selesai,
            'waktu_selesai_kerja' => now(),
        ]);
    }

    public function simpanDiRak(string $lokasi = ''): void
    {
        $this->update([
            'status_produksi' => StatusProduksi::Disimpan,
            'lokasi_rak'      => $lokasi,
            'waktu_disimpan'  => now(),
        ]);
    }

    public function tandaiDiambil(): void
    {
        $this->update([
            'status_produksi' => StatusProduksi::Diambil,
            'waktu_diambil'   => now(),
        ]);
    }
}
