<?php

namespace App\Services;

use App\Enums\KategoriPengeluaran;
use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\PenyebabSelisihStok;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\GantiRugi;
use App\Models\GantiRugiPotongan;
use App\Models\Karyawan;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Penggajian;
use App\Models\StokOpname;
use App\Models\StokOpnameRincian;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Support\ClosingLock;
use App\Support\StokOpnameAkun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Ganti rugi karyawan untuk barang Hilang (RANCANGAN_STOK_OPNAME.md bagian 6).
 * - Tunai → kas lokasi masuk, Pemulihan Kerugian Stok (OPN-PULIH).
 * - Potong gaji → cicilan 1–12 bulan; potongan dihitung saat slip gaji dibuat
 *   (tidak melebihi gaji bersih; slip draft dihitung ulang → potongan dibuat
 *   ulang, tidak dobel). Saat slip DIBAYAR: 2 baris tanpa kas — tambahan Beban
 *   Gaji (Beban Gaji = gaji kotor) + Pemulihan Kerugian Stok. Batal Bayar slip
 *   membatalkan keduanya.
 */
class GantiRugiService
{
    public static function siap(): bool
    {
        return Schema::hasTable('ganti_rugi');
    }

    public function bolehKelola(User $user, int $cabangId): bool
    {
        return $user->hasPermission('stok_opname.ganti_rugi') && StokOpnameService::bolehAksesLokasi($user, $cabangId);
    }

    /** Nilai Hilang pada opname, sudah dibebankan, dan sisa yang belum dibebankan. */
    public function ringkasOpname(StokOpname $o): array
    {
        $hilang = (float) StokOpnameRincian::whereHas('opnameItem', fn ($q) => $q->where('stok_opname_id', $o->id))
            ->where('penyebab', PenyebabSelisihStok::Hilang->value)->sum('nilai');
        $dibebankan = self::siap() ? (float) GantiRugi::where('stok_opname_id', $o->id)->where('status', '<>', 'batal')->sum('nilai') : 0.0;

        return ['hilang' => round($hilang, 2), 'dibebankan' => round($dibebankan, 2), 'sisa' => max(0, round($hilang - $dibebankan, 2))];
    }

    public static function karyawanUntuk(int $cabangId): Collection
    {
        return Karyawan::where('status', 'aktif')->where('cabang_id', $cabangId)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'cabang_id']);
    }

    /**
     * @param array{karyawan_id:int, nilai:mixed, alasan_nilai?:?string, cara:string, jumlah_cicilan?:int, kas_id?:?int} $data
     */
    public function buat(StokOpname $o, array $data, User $user): GantiRugi
    {
        if (! $this->bolehKelola($user, $o->cabang_id)) {
            abort(403, 'Anda tidak punya izin mengelola ganti rugi karyawan di lokasi ini.');
        }
        if ($o->status !== StokOpname::DISETUJUI) {
            throw new RuntimeException('Ganti rugi hanya bisa dibebankan setelah opname disetujui.');
        }
        $r = $this->ringkasOpname($o);
        if ($r['hilang'] <= 0) {
            throw new RuntimeException('Opname ini tidak punya barang Hilang.');
        }
        $karyawan = self::karyawanUntuk($o->cabang_id)->firstWhere('id', (int) ($data['karyawan_id'] ?? 0));
        if (! $karyawan) {
            throw new RuntimeException('Pilih karyawan aktif di lokasi ini.');
        }
        $nilai = round((float) str_replace(['.', ','], ['', '.'], (string) ($data['nilai'] ?? 0)), 2);
        if ($nilai <= 0) {
            throw new RuntimeException('Nilai ganti rugi harus lebih dari 0.');
        }
        if ($nilai > $r['sisa'] + 0.5) {
            throw new RuntimeException('Nilai melebihi sisa barang hilang yang belum dibebankan (Rp ' . number_format($r['sisa'], 0, ',', '.') . ').');
        }
        $alasan = trim((string) ($data['alasan_nilai'] ?? ''));
        if (abs($nilai - $r['sisa']) > 0.5 && $alasan === '') {
            throw new RuntimeException('Nilai berbeda dari harga pokok barang hilang (Rp ' . number_format($r['sisa'], 0, ',', '.') . ') — tulis alasannya (mis. dibagi 2 orang).');
        }
        $cara = ($data['cara'] ?? '') === 'tunai' ? 'tunai' : (($data['cara'] ?? '') === 'potong_gaji' ? 'potong_gaji' : null);
        if (! $cara) {
            throw new RuntimeException('Pilih cara pembayaran: tunai atau potong gaji.');
        }
        $cicilan = $cara === 'potong_gaji' ? (int) ($data['jumlah_cicilan'] ?? 1) : 1;
        if ($cicilan < 1 || $cicilan > 12) {
            throw new RuntimeException('Cicilan potong gaji 1 sampai 12 bulan.');
        }
        $kas = null;
        if ($cara === 'tunai') {
            $kas = Kas::where('cabang_id', $o->cabang_id)->where('is_active', true)->find((int) ($data['kas_id'] ?? 0));
            if (! $kas) {
                throw new RuntimeException('Pilih kas lokasi tempat uang ganti rugi diterima.');
            }
            if (ClosingLock::terkunci($o->cabang_id, today())) {
                throw new RuntimeException('Hari ini sudah di-Closing / Tutup Buku untuk lokasi ini. Buka kunci Closing hari ini, atau catat besok.');
            }
        }

        return DB::transaction(function () use ($o, $karyawan, $nilai, $alasan, $cara, $cicilan, $kas, $user) {
            $g = GantiRugi::create([
                'stok_opname_id' => $o->id, 'cabang_id' => $o->cabang_id, 'karyawan_id' => $karyawan->id,
                'nilai' => $nilai, 'alasan_nilai' => $alasan ?: null, 'cara' => $cara, 'jumlah_cicilan' => $cicilan,
                'mulai_periode' => $cara === 'potong_gaji' ? today()->addMonthNoOverflow()->format('Y-m') : null,
                'status' => 'berjalan', 'kas_id' => $kas?->id, 'dibuat_by' => $user->id,
            ]);
            if ($cara === 'tunai') {
                $tk = TransaksiKeuangan::create([
                    'cabang_id' => $o->cabang_id, 'kas_id' => $kas->id,
                    'nomor_transaksi' => 'GRT-' . now()->format('Ymd') . '-' . str_pad((string) $g->id, 5, '0', STR_PAD_LEFT),
                    'tanggal_transaksi' => today()->toDateString(), 'tipe' => TipeTransaksiKeuangan::Pemasukan,
                    'kategori' => KategoriEnum::Lainnya, 'kategori_id' => StokOpnameAkun::kategoriId('OPN-PULIH'),
                    'keterangan' => "Ganti rugi barang hilang ({$o->nomor}) — {$karyawan->nama_lengkap}, tunai",
                    'jumlah' => $nilai, 'referensi_type' => StokOpnameService::REF, 'referensi_id' => $o->id, 'created_by' => $user->id,
                ]);
                Kas::whereKey($kas->id)->increment('saldo_sekarang', $nilai);
                $g->update(['terbayar' => $nilai, 'status' => 'lunas', 'transaksi_keuangan_id' => $tk->id]);
            }
            activity('GantiRugi')->performedOn($g)->causedBy($user)
                ->log("Ganti rugi {$o->nomor}: {$karyawan->nama_lengkap} Rp " . number_format($nilai, 0, ',', '.') . " ({$g->labelCara()})");

            return $g->fresh();
        });
    }

    public function batal(GantiRugi $g, string $alasan, User $user): GantiRugi
    {
        if (! $this->bolehKelola($user, $g->cabang_id)) {
            abort(403);
        }
        if ($g->status !== 'berjalan' || (float) $g->terbayar > 0) {
            throw new RuntimeException('Ganti rugi yang sudah ada pembayarannya (tunai/cicilan dibayar) tidak bisa dibatalkan.');
        }
        if (trim($alasan) === '') {
            throw new RuntimeException('Alasan pembatalan wajib diisi.');
        }
        $diSlipFinal = $g->potongan()->whereHas('penggajian', fn ($q) => $q->where('status', '<>', 'draft'))->exists();
        if ($diSlipFinal) {
            throw new RuntimeException('Potongan ganti rugi ini sudah masuk slip gaji yang disetujui. Batalkan persetujuan/hapus slip itu dulu.');
        }

        return DB::transaction(function () use ($g, $alasan, $user) {
            foreach ($g->potongan()->with('penggajian')->get() as $p) {
                $slip = $p->penggajian;
                if ($slip && ! $slip->trashed()) {
                    $slip->update([
                        'potongan_ganti_rugi' => max(0, (float) $slip->potongan_ganti_rugi - (float) $p->nominal),
                        'total_gaji'          => (float) $slip->total_gaji + (float) $p->nominal,
                    ]);
                }
                $p->delete();
            }
            $g->update(['status' => 'batal', 'dibatalkan_at' => now(), 'dibatalkan_by' => $user->id, 'alasan_batal' => trim($alasan)]);
            activity('GantiRugi')->performedOn($g)->causedBy($user)->log("Membatalkan ganti rugi #{$g->id}: {$alasan}");

            return $g->fresh();
        });
    }

    // ===================== Penggajian =====================

    /**
     * Dipanggil PenggajianService::generate() setelah slip (draft) disimpan.
     * Potongan lama milik slip ini dihapus lalu dihitung ulang → tidak dobel.
     */
    public function terapkanKeSlip(Penggajian $slip, float $gajiBersih): Penggajian
    {
        if (! self::siap()) {
            return $slip;
        }
        GantiRugiPotongan::where('penggajian_id', $slip->id)->where('status', 'di_slip')->delete();
        $tersedia = max(0, round($gajiBersih, 2));
        $total = 0.0;

        $daftar = GantiRugi::where('karyawan_id', $slip->karyawan_id)->where('cara', 'potong_gaji')
            ->where('status', 'berjalan')->where('mulai_periode', '<=', $slip->periode)->orderBy('id')->get();
        foreach ($daftar as $g) {
            if ($tersedia <= 0) {
                break;
            }
            $sudah = (float) GantiRugiPotongan::where('ganti_rugi_id', $g->id)
                ->where(fn ($q) => $q->where('status', 'dibayar')
                    ->orWhereHas('penggajian', fn ($s) => $s->whereNull('deleted_at')))
                ->sum('nominal');
            $sisa = round((float) $g->nilai - $sudah, 2);
            if ($sisa <= 0) {
                continue;
            }
            $target = ceil((float) $g->nilai / max(1, $g->jumlah_cicilan));
            $nominal = round(min($target, $sisa, $tersedia), 2);
            if ($nominal <= 0) {
                continue;
            }
            GantiRugiPotongan::create(['ganti_rugi_id' => $g->id, 'penggajian_id' => $slip->id, 'periode' => $slip->periode,
                'nominal' => $nominal, 'status' => 'di_slip']);
            $tersedia -= $nominal;
            $total += $nominal;
        }

        $slip->update(['potongan_ganti_rugi' => round($total, 2), 'total_gaji' => max(0, round($gajiBersih - $total, 2))]);

        return $slip;
    }

    /** Slip dibayar: potongan dianggap diterima → Beban Gaji tambahan + Pemulihan Kerugian Stok (tanpa kas). */
    public function slipDibayar(Penggajian $slip, string $tanggal, ?int $userId): void
    {
        if (! self::siap()) {
            return;
        }
        $gaji = KategoriTransaksi::where('kode', 'GAJI')->first();
        $nama = $slip->karyawan?->nama_lengkap ?? ('Karyawan #' . $slip->karyawan_id);
        foreach (GantiRugiPotongan::with('gantiRugi.opname')->where('penggajian_id', $slip->id)->where('status', 'di_slip')->get() as $p) {
            $g = $p->gantiRugi;
            $nomor = $g->opname?->nomor;
            $tkGaji = TransaksiKeuangan::create([
                'cabang_id' => $slip->cabang_id, 'kas_id' => null,
                'nomor_transaksi' => 'GRG-' . str_pad((string) $p->id, 7, '0', STR_PAD_LEFT),
                'tanggal_transaksi' => $tanggal, 'tipe' => TipeTransaksiKeuangan::Pengeluaran,
                'kategori' => KategoriEnum::Gaji, 'kategori_id' => $gaji?->id, 'kategori_pengeluaran' => KategoriPengeluaran::GajiUpah,
                'keterangan' => "Gaji {$nama} periode {$slip->periode} — bagian yang dipotong untuk ganti rugi stok",
                'jumlah' => (float) $p->nominal, 'referensi_type' => 'penggajian', 'referensi_id' => $slip->id, 'created_by' => $userId,
            ]);
            $tkPulih = TransaksiKeuangan::create([
                'cabang_id' => $g->cabang_id, 'kas_id' => null,
                'nomor_transaksi' => 'GRP-' . str_pad((string) $p->id, 7, '0', STR_PAD_LEFT),
                'tanggal_transaksi' => $tanggal, 'tipe' => TipeTransaksiKeuangan::Pemasukan,
                'kategori' => KategoriEnum::Lainnya, 'kategori_id' => StokOpnameAkun::kategoriId('OPN-PULIH'),
                'keterangan' => "Ganti rugi barang hilang" . ($nomor ? " ({$nomor})" : '') . " — {$nama}, potong gaji {$slip->periode}",
                'jumlah' => (float) $p->nominal, 'referensi_type' => StokOpnameService::REF, 'referensi_id' => $g->stok_opname_id, 'created_by' => $userId,
            ]);
            $p->update(['status' => 'dibayar', 'tk_gaji_id' => $tkGaji->id, 'tk_pulih_id' => $tkPulih->id]);
            $terbayar = round((float) $g->terbayar + (float) $p->nominal, 2);
            $g->update(['terbayar' => $terbayar, 'status' => $terbayar + 0.5 >= (float) $g->nilai ? 'lunas' : 'berjalan']);
        }
    }

    /** Batal Bayar slip: kedua baris tanpa kas dihapus, potongan kembali "di slip". */
    public function slipBatalBayar(Penggajian $slip): void
    {
        if (! self::siap()) {
            return;
        }
        foreach (GantiRugiPotongan::with('gantiRugi')->where('penggajian_id', $slip->id)->where('status', 'dibayar')->get() as $p) {
            foreach (array_filter([$p->tk_gaji_id, $p->tk_pulih_id]) as $id) {
                TransaksiKeuangan::find($id)?->forceDelete();
            }
            $p->update(['status' => 'di_slip', 'tk_gaji_id' => null, 'tk_pulih_id' => null]);
            $g = $p->gantiRugi;
            $g->update(['terbayar' => max(0, round((float) $g->terbayar - (float) $p->nominal, 2)), 'status' => 'berjalan']);
        }
    }

    /** Slip draft dihapus: potongannya dilepas. */
    public function slipDihapus(Penggajian $slip): void
    {
        if (self::siap()) {
            GantiRugiPotongan::where('penggajian_id', $slip->id)->where('status', 'di_slip')->delete();
        }
    }
}
