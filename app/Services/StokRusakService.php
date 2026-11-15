<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\PenyebabSelisihStok;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Kas;
use App\Models\StokRusak;
use App\Models\StokRusakTindakan;
use App\Models\Supplier;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Support\ClosingLock;
use App\Support\StokOpnameAkun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tindak lanjut stok rusak (RANCANGAN_STOK_OPNAME.md bagian 5). Kerugian sudah
 * dicatat saat opname disetujui:
 * - Diganti barang / Dipakai lagi → stok jual masuk lagi (harga pokok semula),
 *   kerugian dibatalkan = baris MINUS di akun beban penyebabnya (tanpa kas).
 * - Dibayar uang / Dipotong tagihan → kas masuk, Pemulihan Kerugian Stok.
 * - Dimusnahkan → hanya keluar dari stok rusak (foto + Berita Acara Pemusnahan).
 */
class StokRusakService
{
    public const LABEL_TINDAKAN = [
        'ganti_barang'   => 'Kembali ke pemasok — diganti barang',
        'bayar_uang'     => 'Kembali ke pemasok — dibayar uang',
        'potong_tagihan' => 'Kembali ke pemasok — dipotong tagihan',
        'musnahkan'      => 'Dimusnahkan',
        'pakai_lagi'     => 'Dipakai lagi',
    ];

    public function __construct(private StokService $stok) {}

    public function bolehKelola(User $user, StokRusak $s): bool
    {
        return $user->hasPermission('stok_opname.stok_rusak') && StokOpnameService::bolehAksesLokasi($user, $s->cabang_id);
    }

    /** Pilihan tindakan untuk baris ini (Dipakai lagi hanya untuk penyebab Rusak — P8). */
    public static function pilihan(StokRusak $s): array
    {
        $j = self::LABEL_TINDAKAN;
        if ($s->penyebab !== PenyebabSelisihStok::Rusak) {
            unset($j['pakai_lagi']);
        }

        return $j;
    }

    public static function kasLokasi(int $cabangId): Collection
    {
        return Kas::where('cabang_id', $cabangId)->where('is_active', true)->orderBy('nama_kas')->get();
    }

    private function nomorBa(): string
    {
        $prefix = 'BA-' . now()->format('Ym') . '-';
        $last = StokRusakTindakan::where('nomor_ba', 'like', $prefix . '%')->lockForUpdate()->max('nomor_ba');

        return $prefix . str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array{jenis:string, qty:mixed, uang?:mixed, kas_id?:?int, supplier_id?:?int, nomor_po?:?string, saksi?:?string, catatan?:?string, foto?:?UploadedFile} $data
     */
    public function tindak(StokRusak $s, array $data, User $user): StokRusakTindakan
    {
        if (! $this->bolehKelola($user, $s)) {
            abort(403, 'Anda tidak punya izin mengelola stok rusak di lokasi ini.');
        }
        $jenis = (string) ($data['jenis'] ?? '');
        if (! array_key_exists($jenis, self::pilihan($s))) {
            throw new RuntimeException($jenis === 'pakai_lagi'
                ? 'Barang basi, kedaluwarsa, atau kena freezer mati tidak boleh dipakai lagi — musnahkan atau kembalikan ke pemasok.'
                : 'Pilih tindakan.');
        }
        $qty = round((float) str_replace(',', '.', (string) ($data['qty'] ?? 0)), 3);
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah harus lebih dari 0.');
        }
        if (! StokOpnameService::bolehDesimal($s->item?->satuan) && abs($qty - round($qty)) > 0.0001) {
            throw new RuntimeException("Satuan {$s->item?->satuan} harus bilangan bulat.");
        }

        $uangDiterima = in_array($jenis, ['bayar_uang', 'potong_tagihan'], true);
        $catatKeuangan = $uangDiterima || in_array($jenis, ['ganti_barang', 'pakai_lagi'], true);
        $kas = null;
        $uang = 0.0;
        if ($uangDiterima) {
            $uang = round((float) str_replace(['.', ','], ['', '.'], (string) ($data['uang'] ?? 0)), 2);
            if ($uang <= 0) {
                throw new RuntimeException('Isi jumlah uang yang diterima dari pemasok.');
            }
            $kas = self::kasLokasi($s->cabang_id)->firstWhere('id', (int) ($data['kas_id'] ?? 0));
            if (! $kas) {
                throw new RuntimeException($jenis === 'potong_tagihan'
                    ? 'Pilih kas yang dipakai membayar PO tersebut.' : 'Pilih kas tempat uang diterima.');
            }
        }
        if ($jenis === 'potong_tagihan' && trim((string) ($data['nomor_po'] ?? '')) === '') {
            throw new RuntimeException('Nomor PO / faktur wajib diisi untuk potong tagihan.');
        }
        if ($jenis === 'musnahkan' && ! (($data['foto'] ?? null) instanceof UploadedFile)) {
            throw new RuntimeException('Foto pemusnahan wajib dilampirkan.');
        }
        $supplierId = ! empty($data['supplier_id']) ? Supplier::whereKey((int) $data['supplier_id'])->value('id') : null;
        if ($catatKeuangan && ClosingLock::terkunci($s->cabang_id, today())) {
            throw new RuntimeException('Hari ini sudah di-Closing / Tutup Buku untuk lokasi ini. Buka kunci Closing hari ini, atau lakukan besok.');
        }

        return DB::transaction(function () use ($s, $jenis, $qty, $uang, $kas, $supplierId, $data, $user, $uangDiterima) {
            $s = StokRusak::lockForUpdate()->findOrFail($s->id);
            if ($s->qty_sisa + 0.0005 < $qty) {
                throw new RuntimeException('Jumlah melebihi stok rusak yang tersisa (' . StokOpnameService::angka((float) $s->qty_sisa) . ').');
            }
            $nilai = round($qty * (float) $s->harga_pokok, 2);
            $opname = $s->opname();
            $nama = $s->item?->nama_item;
            $satuan = $s->item?->satuan;
            $label = self::LABEL_TINDAKAN[$jenis];
            $foto = ($data['foto'] ?? null) instanceof UploadedFile ? $data['foto']->store('stok-rusak/' . $s->id, 'public') : null;

            $tindakan = StokRusakTindakan::create([
                'stok_rusak_id' => $s->id, 'jenis' => $jenis, 'qty' => $qty, 'nilai' => $nilai, 'uang' => $uang,
                'kas_id' => $kas?->id, 'supplier_id' => $supplierId,
                'nomor_po' => trim((string) ($data['nomor_po'] ?? '')) ?: null,
                'nomor_ba' => $jenis === 'musnahkan' ? $this->nomorBa() : null,
                'foto_path' => $foto, 'saksi' => trim((string) ($data['saksi'] ?? '')) ?: null,
                'catatan' => trim((string) ($data['catatan'] ?? '')) ?: null,
                'tanggal' => today(), 'user_id' => $user->id,
            ]);

            $ref = $opname ? ['stok_opname', $opname->id] : ['stok_rusak', $s->id];
            $tk = null;
            $mv = null;
            if (in_array($jenis, ['ganti_barang', 'pakai_lagi'], true)) {
                $mv = $this->stok->tambahPenyesuaian($s->item_id, $s->cabang_id, $qty, (float) $s->harga_pokok,
                    "Stok rusak kembali ke stok jual ({$label})" . ($opname ? " — {$opname->nomor}" : ''), $ref[0], $ref[1]);
                // Kerugian dibatalkan: baris minus di akun beban penyebab yang sama
                $tk = $nilai > 0 ? TransaksiKeuangan::create([
                    'cabang_id' => $s->cabang_id, 'kas_id' => null,
                    'nomor_transaksi' => 'SRK-' . now()->format('Ymd') . '-' . str_pad((string) $tindakan->id, 5, '0', STR_PAD_LEFT),
                    'tanggal_transaksi' => today()->toDateString(), 'tipe' => TipeTransaksiKeuangan::Pengeluaran,
                    'kategori' => KategoriEnum::Lainnya, 'kategori_id' => StokOpnameAkun::kategoriId($s->penyebab->kodeKategori()),
                    'keterangan' => "Kerugian stok dibatalkan — {$nama} " . StokOpnameService::angka($qty) . " {$satuan} ({$label})",
                    'jumlah' => -$nilai, 'referensi_type' => $ref[0], 'referensi_id' => $ref[1], 'created_by' => $user->id,
                ]) : null;
            } elseif ($uangDiterima) {
                $tk = TransaksiKeuangan::create([
                    'cabang_id' => $s->cabang_id, 'kas_id' => $kas->id,
                    'nomor_transaksi' => 'SRK-' . now()->format('Ymd') . '-' . str_pad((string) $tindakan->id, 5, '0', STR_PAD_LEFT),
                    'tanggal_transaksi' => today()->toDateString(), 'tipe' => TipeTransaksiKeuangan::Pemasukan,
                    'kategori' => KategoriEnum::Lainnya, 'kategori_id' => StokOpnameAkun::kategoriId('OPN-PULIH'),
                    'keterangan' => "Pemulihan kerugian stok — {$nama} " . StokOpnameService::angka($qty) . " {$satuan} ({$label})"
                        . ($tindakan->nomor_po ? " PO {$tindakan->nomor_po}" : ''),
                    'jumlah' => $uang, 'referensi_type' => $ref[0], 'referensi_id' => $ref[1], 'created_by' => $user->id,
                ]);
                Kas::whereKey($kas->id)->increment('saldo_sekarang', $uang);
            }

            $tindakan->update(['transaksi_keuangan_id' => $tk?->id, 'stock_movement_id' => $mv]);
            $sisa = round((float) $s->qty_sisa - $qty, 3);
            $s->update(['qty_sisa' => max(0, $sisa), 'status' => $sisa <= 0.0005 ? 'selesai' : 'tertahan']);

            activity('StokRusak')->performedOn($s)->causedBy($user)
                ->log("{$label}: {$nama} " . StokOpnameService::angka($qty) . " {$satuan}" . ($uang > 0 ? ' (uang Rp ' . number_format($uang, 0, ',', '.') . ')' : ''));

            return $tindakan;
        });
    }
}
