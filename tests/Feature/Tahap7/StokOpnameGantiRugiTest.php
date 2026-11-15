<?php

namespace Tests\Feature\Tahap7;

use App\Models\GantiRugi;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Kas;
use App\Models\StokOpname;
use App\Models\TransaksiKeuangan;
use App\Services\PenggajianService;
use Illuminate\Support\Facades\DB;

/** Stok Opname Tahap D — ganti rugi karyawan + Pengaturan Stok Opname. */
class StokOpnameGantiRugiTest extends StokOpnameTestCase
{
    private StokOpname $o;
    private Kas $kas;
    private Karyawan $karyawan;
    private string $periodeDepan;

    protected function setUp(): void
    {
        parent::setUp();
        $item = $this->barang('Udang D', 'pcs', [[10, 1000]]);
        $this->o = $this->mulai([$item]);
        $this->hitung($this->o, $item, 7); // hilang 3 = Rp 3.000
        $this->rincian($this->o, $item, [['penyebab' => 'hilang', 'qty' => 3]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $this->o));
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $this->o))->assertSessionHas('success');
        $this->kas = Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => 'Kas Uji D ' . uniqid(), 'tipe_kas' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 1000000, 'is_active' => true]);
        $this->karyawan = Karyawan::create([
            'cabang_id' => $this->cabang->id, 'nik' => 'KGR' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan Ganti Rugi',
            'jenis_kelamin' => 'L', 'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => '2026-01-01',
            'gaji_pokok' => 3000000, 'status' => 'aktif',
        ]);
        $this->periodeDepan = today()->addMonthNoOverflow()->format('Y-m');
    }

    private function bebankan(array $data, $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->post(route('ganti-rugi.store', $this->o), $data + ['karyawan_id' => $this->karyawan->id]);
    }

    public function test_tunai_masuk_kas_dan_tidak_bisa_dibatalkan(): void
    {
        $this->bebankan(['nilai' => 3000, 'cara' => 'tunai', 'kas_id' => $this->kas->id])->assertSessionHas('success');
        $this->assertEquals(1003000, (float) $this->kas->fresh()->saldo_sekarang);
        $g = GantiRugi::where('stok_opname_id', $this->o->id)->firstOrFail();
        $this->assertSame('lunas', $g->status);
        $this->assertSame('OPN-PULIH', DB::table('kategori_transaksis')->where('id', TransaksiKeuangan::find($g->transaksi_keuangan_id)->kategori_id)->value('kode'));
        $this->actingAs($this->admin)->post(route('ganti-rugi.batal', $g), ['alasan' => 'x'])->assertSessionHas('error');
        // Tidak bisa melebihi sisa
        $this->bebankan(['nilai' => 1, 'cara' => 'tunai', 'kas_id' => $this->kas->id])->assertSessionHas('error');
    }

    public function test_nilai_lain_wajib_alasan_dan_izin(): void
    {
        $this->bebankan(['nilai' => 1500, 'cara' => 'potong_gaji', 'jumlah_cicilan' => 1])->assertSessionHas('error');
        $this->bebankan(['nilai' => 1500, 'cara' => 'potong_gaji', 'jumlah_cicilan' => 1, 'alasan_nilai' => 'dibagi 2 orang'])->assertSessionHas('success');
        $this->bebankan(['nilai' => 1500, 'cara' => 'potong_gaji', 'jumlah_cicilan' => 1], $this->manajer)->assertForbidden();
        $this->actingAs($this->manajer)->get(route('ganti-rugi.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('ganti-rugi.index'))->assertOk()->assertSee('Karyawan Ganti Rugi');
        $this->actingAs($this->admin)->get(route('stok-opname.show', $this->o))->assertOk()->assertSee('Ganti rugi karyawan');
    }

    public function test_potong_gaji_dicicil_tidak_dobel_dan_dicatat_saat_dibayar(): void
    {
        $this->bebankan(['nilai' => 3000, 'cara' => 'potong_gaji', 'jumlah_cicilan' => 3])->assertSessionHas('success');
        $g = GantiRugi::where('stok_opname_id', $this->o->id)->firstOrFail();
        $this->assertSame($this->periodeDepan, $g->mulai_periode);

        $svc = app(PenggajianService::class);
        $slipBulanIni = $svc->generate($this->karyawan, today()->format('Y-m'));
        $this->assertEquals(0, (float) $slipBulanIni->potongan_ganti_rugi, 'belum mulai bulan ini');
        $slipBulanIni->forceDelete();

        $slip = $svc->generate($this->karyawan, $this->periodeDepan);
        $this->assertEquals(1000, (float) $slip->potongan_ganti_rugi);
        $bersihTanpa = (float) $slip->total_gaji + 1000;
        $slip = $svc->generate($this->karyawan, $this->periodeDepan); // dihitung ulang
        $this->assertEquals(1000, (float) $slip->potongan_ganti_rugi, 'tidak dobel');
        $this->assertSame(1, DB::table('ganti_rugi_potongan')->where('ganti_rugi_id', $g->id)->count());
        $this->assertEqualsWithDelta($bersihTanpa - 1000, (float) $slip->total_gaji, 0.01);

        $this->actingAs($this->buatUser('owner'));
        $saldo = (float) $this->kas->fresh()->saldo_sekarang;
        $svc->bayar($slip, $this->kas->id, today()->toDateString(), auth()->id());
        $this->assertEqualsWithDelta($saldo - (float) $slip->total_gaji, (float) $this->kas->fresh()->saldo_sekarang, 0.01, 'kas hanya gaji bersih');
        $g->refresh();
        $this->assertEquals(1000, (float) $g->terbayar);
        $this->assertSame('berjalan', $g->status);
        $nonKas = TransaksiKeuangan::whereNull('kas_id')->where('nomor_transaksi', 'like', 'GR%')->get();
        $this->assertCount(2, $nonKas);
        $this->assertEquals(2000, (float) $nonKas->sum('jumlah'));

        $svc->batalBayar($slip->fresh());
        $this->assertEquals(0, (float) $g->fresh()->terbayar);
        $this->assertSame(0, TransaksiKeuangan::whereNull('kas_id')->where('nomor_transaksi', 'like', 'GR%')->count());
    }

    public function test_potongan_tidak_melebihi_gaji_bersih_dan_batal_mengembalikan_slip(): void
    {
        $this->karyawan->update(['gaji_pokok' => 2000]); // gaji bersih kecil
        $this->bebankan(['nilai' => 3000, 'cara' => 'potong_gaji', 'jumlah_cicilan' => 1])->assertSessionHas('success');
        $slip = app(PenggajianService::class)->generate($this->karyawan, $this->periodeDepan);
        $this->assertGreaterThanOrEqual(0, (float) $slip->total_gaji);
        $this->assertLessThanOrEqual(2000, (float) $slip->potongan_ganti_rugi);
        $this->assertEquals(0.0, (float) $slip->total_gaji);

        $g = GantiRugi::where('stok_opname_id', $this->o->id)->firstOrFail();
        $potongan = (float) $slip->potongan_ganti_rugi;
        $this->actingAs($this->admin)->post(route('ganti-rugi.batal', $g), ['alasan' => 'salah orang'])->assertSessionHas('success');
        $slip->refresh();
        $this->assertEquals(0, (float) $slip->potongan_ganti_rugi);
        $this->assertEqualsWithDelta($potongan, (float) $slip->total_gaji, 0.01);
    }

    public function test_pengaturan_stok_opname_tersimpan_dan_dicatat(): void
    {
        $owner = $this->buatUser('owner');
        $s = \App\Models\PengaturanUmum::query()->first();
        $this->actingAs($owner)->put(route('pengaturan.umum.update'), [
            'nama_perusahaan' => $s->nama_perusahaan, 'opname_form' => 1,
            'opname_batas_foto_hilang' => 0, 'opname_pengingat_aktif' => 1, 'opname_pengingat_hari' => 5, 'opname_pengingat_jam' => '09:30',
        ])->assertRedirect();
        $s->refresh();
        $this->assertFalse((bool) $s->opname_hitung_buta);
        $this->assertEquals(0, (float) $s->opname_batas_foto_hilang);
        $this->assertSame(5, (int) $s->opname_pengingat_hari);
        $this->assertTrue(DB::table('activity_log')->where('description', 'Ubah pengaturan Stok Opname')->exists());

        // Hitung buta mati → penghitung melihat stok sistem
        $item = $this->barang('Garam D', 'pcs', [[10, 100]]);
        $o = $this->mulai([$item]);
        $this->assertFalse($o->hitung_buta);
        $this->hitung($o, $item, 9)->assertOk()->assertJson(['qty_sistem' => 10]);
        // Batas 0 → hilang murah pun wajib foto
        $this->rincian($o, $item, [['penyebab' => 'hilang', 'qty' => 1]])->assertSessionHas('error');
    }
}
