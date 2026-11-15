<?php

namespace App\Services;

use App\Enums\PenyebabSelisihStok;
use App\Models\Cabang;
use App\Models\StokOpname;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Dashboard Stok Opname (RANCANGAN_STOK_OPNAME.md bagian 7) — murni READ. */
class StokOpnameDashboardService
{
    /** Nama bulan Indonesia (APP_LOCALE bisa en — lihat CLAUDE.md 4.46). */
    public const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    public const BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __construct(private LaporanKerugianStokService $kerugian) {}

    /** @param Collection<int, Cabang> $lokasi */
    public function hitung(Collection $lokasi, ?Carbon $kini = null): array
    {
        $kini ??= now();
        $ids = $lokasi->pluck('id')->all();
        $awal = $kini->copy()->startOfMonth();
        $akhir = $kini->copy()->endOfMonth();

        // Status bulan ini per lokasi: opname terakhir bulan ini (selain dibatalkan)
        $bulanIni = StokOpname::whereIn('cabang_id', $ids)->where('status', '<>', StokOpname::DIBATALKAN)
            ->whereBetween('created_at', [$awal, $akhir])->orderByDesc('id')->get()->groupBy('cabang_id');
        $status = $lokasi->map(function (Cabang $c) use ($bulanIni) {
            $list = $bulanIni->get($c->id, collect());
            $kode = $list->contains('status', StokOpname::DISETUJUI) ? 'sudah'
                : ($list->contains('status', StokOpname::DIAJUKAN) ? 'menunggu'
                : ($list->contains('status', StokOpname::MENGHITUNG) ? 'menghitung' : 'belum'));
            $acuan = $list->firstWhere('status', StokOpname::DISETUJUI) ?? $list->first();

            return (object) ['cabang' => $c, 'kode' => $kode, 'opname' => $acuan];
        })->values();

        $menunggu = StokOpname::with(['cabang', 'pengaju'])->whereIn('cabang_id', $ids)->where('status', StokOpname::DIAJUKAN)
            ->orderBy('diajukan_at')->get();

        // Kerugian per penyebab: bulan ini vs bulan lalu
        $ini = $this->kerugian->hitung($awal->copy(), $akhir->copy(), $ids);
        $lalu = $this->kerugian->hitung($awal->copy()->subMonthNoOverflow()->startOfMonth(), $awal->copy()->subDay(), $ids);
        $penyebab = collect(PenyebabSelisihStok::cases())->map(fn ($p) => (object) [
            'label' => $p->labelPendek(),
            'lebih' => $p === PenyebabSelisihStok::Lebih,
            'ini'   => (float) ($ini['per_penyebab']->firstWhere('penyebab', $p->value)->nilai ?? 0),
            'lalu'  => (float) ($lalu['per_penyebab']->firstWhere('penyebab', $p->value)->nilai ?? 0),
        ])->filter(fn ($x) => $x->ini > 0 || $x->lalu > 0)->values();

        // Akurasi 6 bulan per lokasi (rata-rata opname disetujui per bulan)
        $bulan = collect(range(5, 0))->map(fn ($i) => $kini->copy()->startOfMonth()->subMonthsNoOverflow($i));
        $akurasiRaw = DB::table('stok_opnames')->where('status', 'disetujui')->whereIn('cabang_id', $ids)
            ->whereBetween('tanggal_disetujui', [$bulan->first()->toDateString(), $akhir->toDateString()])
            ->selectRaw("cabang_id, DATE_FORMAT(tanggal_disetujui, '%Y-%m') as bln, AVG(akurasi) as a")
            ->groupBy('cabang_id', 'bln')->get()->groupBy('cabang_id');
        $akurasi = [
            'label' => $bulan->map(fn ($b) => self::BULAN[$b->month - 1] . ' ' . $b->year)->all(),
            'kunci' => $bulan->map(fn ($b) => $b->format('Y-m'))->all(),
            'seri'  => $lokasi->filter(fn ($c) => $akurasiRaw->has($c->id))->map(fn ($c) => [
                'nama' => $c->nama_cabang,
                'data' => $bulan->map(fn ($b) => ($v = $akurasiRaw[$c->id]->firstWhere('bln', $b->format('Y-m'))) ? round((float) $v->a, 1) : null)->all(),
            ])->values()->all(),
        ];

        // 10 barang paling sering selisih (6 bulan)
        $seringSelisih = DB::table('stok_opname_items as i')->join('stok_opnames as o', 'o.id', '=', 'i.stok_opname_id')
            ->join('items as it', 'it.id', '=', 'i.item_id')->join('cabangs as c', 'c.id', '=', 'o.cabang_id')
            ->where('o.status', 'disetujui')->whereIn('o.cabang_id', $ids)->where('i.dikeluarkan', false)
            ->where('o.tanggal_disetujui', '>=', $bulan->first()->toDateString())
            ->whereRaw('ABS(i.qty_fisik - i.qty_sistem) > 0.0005')
            ->groupBy('i.item_id', 'it.nama_item', 'c.nama_cabang')
            ->selectRaw('it.nama_item, c.nama_cabang, COUNT(*) as kali, SUM(CASE WHEN i.nilai_selisih < 0 THEN -i.nilai_selisih ELSE 0 END) as rugi')
            ->orderByDesc('kali')->orderByDesc('rugi')->limit(10)->get();

        return [
            'status'         => $status,
            'menunggu'       => $menunggu,
            'penyebab'       => $penyebab,
            'kerugian_ini'   => $ini,
            'kerugian_lalu'  => $lalu,
            'akurasi'        => $akurasi,
            'sering_selisih' => $seringSelisih,
            'stok_rusak'     => $ini['stok_rusak'],
            'ganti_rugi'     => $ini['ganti_rugi'],
            'pengingat'      => StokOpnamePengingat::dalamMasa($kini),
        ];
    }
}
