<?php

namespace App\Services;

use App\Enums\PenyebabSelisihStok;
use App\Support\StokOpnameAkun;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan Kerugian Stok (RANCANGAN_STOK_OPNAME.md bagian 7) — murni READ.
 * Sumber: rincian Stok Opname yang DISETUJUI (tanggal persetujuan), baris
 * keuangan pemulihan/pembatalan kerugian, stok rusak, ganti rugi karyawan.
 */
class LaporanKerugianStokService
{
    /** @param int[] $cabangIds */
    public function hitung(Carbon $dari, Carbon $sampai, array $cabangIds): array
    {
        $dariS = $dari->toDateString();
        $sampaiS = $sampai->toDateString();
        $lebih = PenyebabSelisihStok::Lebih->value;

        $rincian = fn () => DB::table('stok_opname_rincian as r')
            ->join('stok_opname_items as i', 'i.id', '=', 'r.stok_opname_item_id')
            ->join('stok_opnames as o', 'o.id', '=', 'i.stok_opname_id')
            ->where('o.status', 'disetujui')
            ->whereBetween('o.tanggal_disetujui', [$dariS, $sampaiS])
            ->whereIn('o.cabang_id', $cabangIds);

        $perPenyebab = $rincian()->groupBy('r.penyebab')
            ->selectRaw('r.penyebab, SUM(r.qty) as qty, SUM(r.nilai) as nilai, COUNT(DISTINCT i.item_id) as barang, COUNT(*) as kejadian')
            ->get()->map(function ($x) {
                $p = PenyebabSelisihStok::tryFrom($x->penyebab);
                $x->label = $p?->labelPendek() ?? $x->penyebab;
                $x->nilai = (float) $x->nilai;

                return $x;
            })->sortByDesc('nilai')->values();

        $perLokasi = $rincian()->join('cabangs as c', 'c.id', '=', 'o.cabang_id')->groupBy('o.cabang_id', 'c.nama_cabang')
            ->selectRaw("o.cabang_id, c.nama_cabang, SUM(CASE WHEN r.penyebab <> ? THEN r.nilai ELSE 0 END) as kurang, SUM(CASE WHEN r.penyebab = ? THEN r.nilai ELSE 0 END) as lebih", [$lebih, $lebih])
            ->get()->keyBy('cabang_id');
        $opnamePerLokasi = DB::table('stok_opnames')->where('status', 'disetujui')
            ->whereBetween('tanggal_disetujui', [$dariS, $sampaiS])->whereIn('cabang_id', $cabangIds)
            ->groupBy('cabang_id')->selectRaw('cabang_id, COUNT(*) as jumlah, AVG(akurasi) as akurasi')->get()->keyBy('cabang_id');
        $namaCabang = DB::table('cabangs')->whereIn('id', $cabangIds)->pluck('nama_cabang', 'id');
        $lokasi = collect($cabangIds)->map(fn ($id) => (object) [
            'cabang_id' => $id,
            'nama'      => $namaCabang[$id] ?? '-',
            'opname'    => (int) ($opnamePerLokasi[$id]->jumlah ?? 0),
            'akurasi'   => isset($opnamePerLokasi[$id]) && $opnamePerLokasi[$id]->akurasi !== null ? round((float) $opnamePerLokasi[$id]->akurasi, 2) : null,
            'kurang'    => (float) ($perLokasi[$id]->kurang ?? 0),
            'lebih'     => (float) ($perLokasi[$id]->lebih ?? 0),
        ])->filter(fn ($l) => $l->opname > 0 || $l->kurang > 0)->sortByDesc('kurang')->values();

        $perBarang = $rincian()->join('items as it', 'it.id', '=', 'i.item_id')->join('cabangs as c', 'c.id', '=', 'o.cabang_id')
            ->where('r.penyebab', '<>', $lebih)
            ->groupBy('i.item_id', 'it.nama_item', 'it.kode_item', 'it.satuan', 'c.nama_cabang')
            ->selectRaw('i.item_id, it.nama_item, it.kode_item, it.satuan, c.nama_cabang, SUM(r.qty) as qty, SUM(r.nilai) as nilai, COUNT(DISTINCT o.id) as opname, GROUP_CONCAT(DISTINCT r.penyebab) as penyebab')
            ->orderByDesc('nilai')->limit(50)->get()
            ->map(function ($x) {
                $x->penyebab = collect(explode(',', (string) $x->penyebab))->map(fn ($p) => PenyebabSelisihStok::tryFrom($p)?->labelPendek() ?? $p)->implode(', ');

                return $x;
            });

        // Pemulihan: uang ganti rugi/pemasok (OPN-PULIH) + pembatalan kerugian (baris minus di akun beban opname)
        $kategori = DB::table('kategori_transaksis')->whereIn('kode', array_keys(StokOpnameAkun::KATEGORI))->pluck('id', 'kode');
        $tk = fn () => DB::table('transaksi_keuangans')->whereNull('deleted_at')
            ->whereBetween('tanggal_transaksi', [$dariS, $sampaiS])->whereIn('cabang_id', $cabangIds);
        $dipulihkanUang = (float) $tk()->where('kategori_id', $kategori['OPN-PULIH'] ?? 0)->sum('jumlah');
        $kodeBeban = collect(StokOpnameAkun::KATEGORI)->filter(fn ($k) => $k[1] === 'pengeluaran')->keys();
        $dibatalkan = abs((float) $tk()->whereIn('kategori_id', $kategori->only($kodeBeban->all())->values())->where('jumlah', '<', 0)->sum('jumlah'));

        $kotor = round((float) $perPenyebab->where('penyebab', '<>', $lebih)->sum('nilai'), 2);
        $nilaiLebih = round((float) $perPenyebab->where('penyebab', $lebih)->sum('nilai'), 2);

        return [
            'dari'           => $dari,
            'sampai'         => $sampai,
            'per_penyebab'   => $perPenyebab,
            'per_lokasi'     => $lokasi,
            'per_barang'     => $perBarang,
            'kerugian_kotor' => $kotor,
            'nilai_lebih'    => $nilaiLebih,
            'dipulihkan'     => round($dipulihkanUang + $dibatalkan, 2),
            'dipulihkan_uang' => round($dipulihkanUang, 2),
            'dibatalkan'     => round($dibatalkan, 2),
            'kerugian_bersih' => round($kotor - $dipulihkanUang - $dibatalkan - $nilaiLebih, 2),
            'stok_rusak'     => $this->stokRusak($cabangIds),
            'tindak_lanjut'  => $this->tindakLanjut($dariS, $sampaiS, $cabangIds),
            'ganti_rugi'     => $this->gantiRugi($dariS, $sampaiS, $cabangIds),
        ];
    }

    /** Stok rusak yang masih tertahan sekarang, per lokasi. */
    private function stokRusak(array $cabangIds): Collection
    {
        return DB::table('stok_rusak as s')->join('cabangs as c', 'c.id', '=', 's.cabang_id')
            ->whereIn('s.cabang_id', $cabangIds)->where('s.qty_sisa', '>', 0)
            ->groupBy('s.cabang_id', 'c.nama_cabang')
            ->selectRaw('c.nama_cabang, COUNT(*) as baris, SUM(s.qty_sisa * s.harga_pokok) as nilai, MIN(s.tanggal) as tertua')
            ->get()->map(function ($x) {
                $x->nilai = (float) $x->nilai;
                $x->umur = $x->tertua ? Carbon::parse($x->tertua)->diffInDays(today()) : 0;

                return $x;
            });
    }

    /** Tindak lanjut stok rusak dalam periode (Tahap C). */
    private function tindakLanjut(string $dari, string $sampai, array $cabangIds): Collection
    {
        if (! Schema::hasTable('stok_rusak_tindakan')) {
            return collect();
        }

        return DB::table('stok_rusak_tindakan as t')->join('stok_rusak as s', 's.id', '=', 't.stok_rusak_id')
            ->whereIn('s.cabang_id', $cabangIds)->whereBetween('t.tanggal', [$dari, $sampai])
            ->groupBy('t.jenis')->selectRaw('t.jenis, SUM(t.qty) as qty, SUM(t.nilai) as nilai, SUM(t.uang) as uang, COUNT(*) as kali')
            ->get();
    }

    /** Ganti rugi karyawan dalam periode (Tahap D). */
    private function gantiRugi(string $dari, string $sampai, array $cabangIds): ?object
    {
        if (! Schema::hasTable('ganti_rugi')) {
            return null;
        }
        $g = DB::table('ganti_rugi')->whereIn('cabang_id', $cabangIds)->where('status', '<>', 'batal')
            ->whereBetween(DB::raw('DATE(created_at)'), [$dari, $sampai]);

        return (object) [
            'dibebankan' => (float) (clone $g)->sum('nilai'),
            'diterima'   => (float) (clone $g)->sum('terbayar'),
            'sisa'       => (float) DB::table('ganti_rugi')->whereIn('cabang_id', $cabangIds)->where('status', 'berjalan')->selectRaw('SUM(nilai - terbayar) as s')->value('s'),
        ];
    }
}
