<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\PengaturanGaji;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenggajianService
{
    /**
     * Tarif lembur default dari PengaturanGaji; fallback ke formula gaji/173×1.5 jika tarif = 0.
     */
    public function hitungTarifLembur(float $gajiPokok): float
    {
        $tarif = PengaturanGaji::getSetting('tarif_lembur_per_jam', 0);
        return $tarif > 0 ? $tarif : round($gajiPokok / 173 * 1.5);
    }

    /**
     * Hitung PPh21 sederhana (flat progresif berdasarkan PKP setahun)
     * Tarif: 5% s/d 60jt, 15% 60-250jt, 25% 250-500jt, 30% >500jt
     */
    public function hitungPph21(float $gajiSetahun): float
    {
        $pkp = $gajiSetahun - 54000000; // PTKP TK/0
        if ($pkp <= 0) return 0;

        $pajak = 0;
        if ($pkp <= 60000000)       $pajak = $pkp * 0.05;
        elseif ($pkp <= 250000000)  $pajak = 3000000 + ($pkp - 60000000) * 0.15;
        elseif ($pkp <= 500000000)  $pajak = 31500000 + ($pkp - 250000000) * 0.25;
        else                         $pajak = 93500000 + ($pkp - 500000000) * 0.30;

        return round($pajak / 12); // per bulan
    }

    /**
     * Generate slip gaji untuk 1 karyawan pada periode tertentu
     */
    /** 4.51 — slip yang sudah Disetujui/Dibayar tidak boleh dihitung ulang (dulu nominal tertimpa & status jadi Draft lagi). */
    public const STATUS_TERKUNCI = ['disetujui', 'dibayar'];

    public function slipTerkunci(Karyawan $karyawan, string $periode): ?Penggajian
    {
        return Penggajian::where('karyawan_id', $karyawan->id)->where('periode', $periode)
            ->whereIn('status', self::STATUS_TERKUNCI)->first();
    }

    public function generate(Karyawan $karyawan, string $periode, array $override = []): Penggajian
    {
        if ($terkunci = $this->slipTerkunci($karyawan, $periode)) {
            throw new \RuntimeException("Slip gaji {$karyawan->nama_lengkap} periode {$periode} sudah {$terkunci->status} — tidak bisa dihitung ulang. "
                . ($terkunci->status === 'dibayar' ? 'Klik Batal Bayar di slip itu dulu kalau memang perlu dihitung ulang.' : 'Slip yang sudah disetujui dianggap final.'));
        }

        return DB::transaction(function () use ($karyawan, $periode, $override) {
            [$tahun, $bulan] = explode('-', $periode);
            $tanggalAwal  = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();

            // Hitung hari kerja menggunakan hari_kerja_per_minggu dan hari libur
            $hariKerjaPekan  = (int) (PengaturanGaji::getSetting('hari_kerja_per_minggu', 6) ?? 6);
            $cabangId        = $karyawan->cabang_id;
            $hariLiburSet    = HariLibur::getTanggalLibur(
                $tanggalAwal->format('Y-m-d'),
                $tanggalAkhir->format('Y-m-d'),
                $cabangId
            );

            $hariKerja = 0;
            $current   = $tanggalAwal->copy();
            while ($current <= $tanggalAkhir) {
                $dow = $current->dayOfWeek; // 0=Min, 6=Sab
                $adaLibur = isset($hariLiburSet[$current->format('Y-m-d')]);
                // Hari libur mingguan: Minggu selalu libur; Sabtu libur jika 5 hari/pekan
                $isHariLiburMingguan = ($dow === 0) || ($hariKerjaPekan <= 5 && $dow === 6);
                if (!$isHariLiburMingguan && !$adaLibur) {
                    $hariKerja++;
                }
                $current->addDay();
            }

            // Rekap absensi
            $absensis    = Absensi::where('karyawan_id', $karyawan->id)
                ->whereBetween('tanggal', [$tanggalAwal, $tanggalAkhir])->get();
            $jumlahHadir = $absensis->where('status', 'hadir')->count();
            $jumlahAlpha = $absensis->where('status', 'alpha')->count();
            $jamLembur   = $absensis->sum('jam_lembur');

            // ── PENAMBAHAN ────────────────────────────────────────────────
            $gajiPokok         = (float) $karyawan->gaji_pokok;
            $tunjanganJabatan  = (float) ($override['tunjangan_jabatan']  ?? $karyawan->tunjangan_jabatan  ?? 0);
            $tunjanganMakan    = (float) ($override['tunjangan_makan']    ?? $karyawan->tunjangan_makan    ?? 0);
            $tunjanganTransport= (float) ($override['tunjangan_transport'] ?? $karyawan->tunjangan_transport ?? 0);
            $tunjanganKehadiran= (float) ($override['tunjangan_kehadiran'] ?? 0);
            // Premi kehadiran: hanya dibayar jika hadir >= 80% hari kerja
            if ($tunjanganKehadiran == 0 && $hariKerja > 0 && ($jumlahHadir / $hariKerja) >= 0.8) {
                $tunjanganKehadiran = (float) ($override['tunjangan_kehadiran'] ?? 0);
            }
            // Tarif lembur: override dari array > setting global > formula
            $tarifLembur = isset($override['tarif_lembur_per_jam_override'])
                ? (float) $override['tarif_lembur_per_jam_override']
                : $this->hitungTarifLembur($gajiPokok);

            // Uang lembur: manual override masuk via override['uang_lembur']
            $uangLemburAuto   = round($jamLembur * $tarifLembur);
            $uangLemburManual = isset($override['uang_lembur']) && $override['uang_lembur'] !== '';
            $uangLembur       = $uangLemburManual ? (float) $override['uang_lembur'] : $uangLemburAuto;
            $bonus       = (float) ($override['bonus']   ?? 0);
            $insentif    = (float) ($override['insentif'] ?? 0);
            $thr         = (float) ($override['thr']     ?? 0);
            $komisi      = (float) ($override['komisi']  ?? 0);
            $tunjangan   = (float) ($override['tunjangan'] ?? 0); // tunjangan lain-lain

            // ── PENGURANGAN ───────────────────────────────────────────────
            $potonganAlpaSetting = PengaturanGaji::getSetting('potongan_alpa_per_hari', 0);
            $potonganAbsensiAuto = $potonganAlpaSetting > 0
                ? round($potonganAlpaSetting * $jumlahAlpha)
                : ($hariKerja > 0 ? round($gajiPokok / $hariKerja * $jumlahAlpha) : 0);

            $potonganAbsensiManual = isset($override['potongan_absensi']) && $override['potongan_absensi'] !== '';
            $potonganAbsensi = $potonganAbsensiManual
                ? (float) $override['potongan_absensi']
                : $potonganAbsensiAuto;

            // 4.51 — potongan telat: total menit telat (dari Scan Absensi) × tarif per menit
            // (Pengaturan Penggajian). Dulu tarifnya tersimpan tapi tidak pernah dipakai.
            $menitTelat         = (int) $absensis->where('status', 'hadir')->sum('menit_telat');
            $tarifTelatPerMenit = (float) PengaturanGaji::getSetting('potongan_telat_per_menit', 0);
            $potonganTelat      = isset($override['potongan_telat']) && $override['potongan_telat'] !== ''
                ? (float) $override['potongan_telat']
                : round($menitTelat * $tarifTelatPerMenit);

            // BPJS Kesehatan: % dari gaji pokok (ditanggung karyawan)
            $persenBpjsKes   = (float) ($karyawan->tunjangan_bpjs_kesehatan_persen ?? 1);
            $bpjsKesehatan   = (float) ($override['bpjs_kesehatan'] ?? round($gajiPokok * $persenBpjsKes / 100));

            // BPJS Ketenagakerjaan JHT: % dari gaji pokok
            $persenBpjsTk    = (float) ($karyawan->tunjangan_bpjs_tk_persen ?? 2);
            $bpjsTk          = (float) ($override['bpjs_ketenagakerjaan'] ?? round($gajiPokok * $persenBpjsTk / 100));

            // PPh21: dihitung dari total penghasilan bruto setahun
            $brutoBulan = $gajiPokok + $tunjanganJabatan + $tunjanganMakan + $tunjanganTransport + $uangLembur + $bonus + $insentif + $tunjangan;
            $pph21      = (float) ($override['pph21'] ?? $this->hitungPph21($brutoBulan * 12));

            $kasbon      = (float) ($override['kasbon']       ?? 0);
            $potonganLain= (float) ($override['potongan_lain'] ?? 0);

            // ── TOTAL ─────────────────────────────────────────────────────
            $totalPenambahan  = $gajiPokok + $tunjanganJabatan + $tunjanganMakan + $tunjanganTransport
                + $tunjanganKehadiran + $uangLembur + $bonus + $insentif + $thr + $komisi + $tunjangan;
            $totalPotongan    = $potonganAbsensi + $potonganTelat + $bpjsKesehatan + $bpjsTk + $pph21 + $kasbon + $potonganLain;
            $totalGaji        = max(0, $totalPenambahan - $totalPotongan);

            $slip = Penggajian::updateOrCreate(
                ['karyawan_id' => $karyawan->id, 'periode' => $periode],
                [
                    'cabang_id'            => $karyawan->cabang_id,
                    'jumlah_hari_kerja'    => $hariKerja,
                    'jumlah_hadir'         => $jumlahHadir,
                    'jumlah_alpha'         => $jumlahAlpha,
                    'menit_telat_total'    => $menitTelat,
                    'jam_lembur_total'     => $jamLembur,
                    'gaji_pokok'           => $gajiPokok,
                    'tunjangan_jabatan'    => $tunjanganJabatan,
                    'tunjangan_makan'      => $tunjanganMakan,
                    'tunjangan_transport'  => $tunjanganTransport,
                    'tunjangan_kehadiran'  => $tunjanganKehadiran,
                    'tunjangan'            => $tunjangan,
                    'uang_lembur'          => $uangLembur,
                    'bonus'                => $bonus,
                    'insentif'             => $insentif,
                    'thr'                  => $thr,
                    'komisi'               => $komisi,
                    'potongan_absensi'     => $potonganAbsensi,
                    'potongan_telat'       => $potonganTelat,
                    'bpjs_kesehatan'       => $bpjsKesehatan,
                    'bpjs_ketenagakerjaan' => $bpjsTk,
                    'pph21'                => $pph21,
                    'kasbon'               => $kasbon,
                    'potongan_lain'        => $potonganLain,
                    'total_gaji'                     => $totalGaji,
                    'tarif_lembur_per_jam_override'  => $uangLemburManual ? $tarifLembur : null,
                    'potongan_alpa_per_hari_override' => $potonganAbsensiManual ? ($jumlahAlpha > 0 ? round($potonganAbsensi / $jumlahAlpha) : null) : null,
                    'uang_lembur_manual'             => $uangLemburManual,
                    'potongan_alpa_manual'           => $potonganAbsensiManual,
                    'status'                         => 'draft',
                ]
            );

            // Stok Opname — cicilan ganti rugi karyawan (maks. gaji bersih; dihitung ulang, tidak dobel)
            return app(GantiRugiService::class)->terapkanKeSlip($slip, $totalGaji)->fresh();
        });
    }

    /**
     * Generate untuk semua karyawan aktif di cabang.
     * 4.51 — slip yang sudah Disetujui/Dibayar DILEWATI (tidak ditimpa).
     *
     * @return array{dibuat: int, dilewati: string[]}
     */
    public function bulkGenerate(int $cabangId, string $periode, array $override = []): array
    {
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->get();

        $dibuat = 0;
        $dilewati = [];
        foreach ($karyawans as $k) {
            if ($this->slipTerkunci($k, $periode)) {
                $dilewati[] = $k->nama_lengkap;
                continue;
            }
            $this->generate($k, $periode, $override);
            $dibuat++;
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati];
    }

    // ═════════════════════ 4.51 — PEMBAYARAN GAJI KE KAS ═════════════════════

    /** Kas yang boleh dipakai membayar slip ini: kas aktif cabang slip + kas Gudang Pusat (gaji dibayar pusat). */
    public function kasPembayar(Penggajian $penggajian)
    {
        $cabangIds = \App\Models\Cabang::gudangPusat()->pluck('id')->push($penggajian->cabang_id)->unique()->filter();

        return \App\Models\Kas::aktif()->whereIn('cabang_id', $cabangIds)->with('cabang')
            ->orderByRaw('cabang_id = ? DESC', [$penggajian->cabang_id])->orderBy('nama_kas')->get()
            ->filter(fn ($k) => \App\Support\CabangAktif::bolehAkses($k->cabang_id))->values();
    }

    /**
     * Bayar slip: kas yang dipilih BERKURANG sebesar total gaji (transaksi Pengeluaran
     * kategori Gaji Karyawan, ber-referensi 'penggajian' → tidak bisa diedit dari Kas & Transaksi).
     */
    public function bayar(Penggajian $penggajian, int $kasId, string $tanggal, ?int $userId): Penggajian
    {
        if (! in_array($penggajian->status, ['draft', 'disetujui'], true)) {
            throw new \RuntimeException('Slip gaji ini sudah dibayar atau tidak valid.');
        }
        $kas = $this->kasPembayar($penggajian)->firstWhere('id', $kasId);
        if (! $kas) {
            throw new \RuntimeException('Kas tidak valid — pilih kas aktif milik cabang karyawan atau Gudang Pusat.');
        }
        $jumlah = round((float) $penggajian->total_gaji, 2);

        return DB::transaction(function () use ($penggajian, $kas, $tanggal, $userId, $jumlah) {
            $trx = null;
            if ($jumlah > 0) {
                $kategori = \App\Models\KategoriTransaksi::where('kode', 'GAJI')->first();
                $nama = $penggajian->karyawan?->nama_lengkap ?? ('Karyawan #' . $penggajian->karyawan_id);
                $trx = \App\Models\TransaksiKeuangan::create([
                    'cabang_id'            => $kas->cabang_id,
                    'kas_id'               => $kas->id,
                    'nomor_transaksi'      => $this->nomorTransaksiGaji($tanggal),
                    'tanggal_transaksi'    => $tanggal,
                    'tipe'                 => \App\Enums\TipeTransaksiKeuangan::Pengeluaran,
                    'kategori'             => \App\Enums\KategoriTransaksi::Gaji,
                    'kategori_id'          => $kategori?->id,
                    'kategori_pengeluaran' => \App\Enums\KategoriPengeluaran::GajiUpah,
                    'keterangan'           => "Gaji {$nama} periode {$penggajian->periode}",
                    'jumlah'               => $jumlah,
                    'referensi_type'       => 'penggajian',
                    'referensi_id'         => $penggajian->id,
                    'created_by'           => $userId,
                ]);
                \App\Models\Kas::whereKey($kas->id)->decrement('saldo_sekarang', $jumlah);
            }

            $penggajian->update([
                'status'                => 'dibayar',
                'tanggal_bayar'         => $tanggal,
                'kas_id'                => $kas->id,
                'transaksi_keuangan_id' => $trx?->id,
            ]);
            app(GantiRugiService::class)->slipDibayar($penggajian, $tanggal, $userId);

            return $penggajian;
        });
    }

    /** Batal Bayar: uang kembali ke kas, transaksi gaji dihapus, slip kembali Disetujui (atau Draft). */
    public function batalBayar(Penggajian $penggajian): Penggajian
    {
        if ($penggajian->status !== 'dibayar') {
            throw new \RuntimeException('Slip gaji ini belum dibayar.');
        }

        return DB::transaction(function () use ($penggajian) {
            app(GantiRugiService::class)->slipBatalBayar($penggajian);
            if ($penggajian->transaksi_keuangan_id) {
                $trx = \App\Models\TransaksiKeuangan::find($penggajian->transaksi_keuangan_id);
                if ($trx) {
                    if ($trx->kas_id) {
                        \App\Models\Kas::whereKey($trx->kas_id)->increment('saldo_sekarang', (float) $trx->jumlah);
                    }
                    // hapus permanen (pola Batal Bayar PO) supaya tidak bisa "dipulihkan" dari Data Terhapus
                    // tanpa saldo; lewat model → kunci Closing tetap berlaku
                    $trx->forceDelete();
                }
            }
            $penggajian->update([
                'status'                => $penggajian->approved_by ? 'disetujui' : 'draft',
                'tanggal_bayar'         => null,
                'kas_id'                => null,
                'transaksi_keuangan_id' => null,
            ]);

            return $penggajian;
        });
    }

    private function nomorTransaksiGaji(string $tanggal): string
    {
        $prefix = 'GAJI-' . Carbon::parse($tanggal)->format('Ymd');
        $last = \App\Models\TransaksiKeuangan::withTrashed()->where('nomor_transaksi', 'like', $prefix . '-%')
            ->orderByDesc('id')->value('nomor_transaksi');
        $seq = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return $prefix . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
