<?php

namespace Tests\Feature\Tahap7;

use App\Models\StokOpname;
use App\Services\LabaRugiFormalService;
use App\Services\NeracaService;
use Carbon\Carbon;

/**
 * Stok Opname Tahap B: Berita Acara PDF/Excel, Laporan Kerugian Stok, Laba Rugi
 * Formal per akun, Neraca tetap seimbang, tautan nomor SO di Kartu Stok & Kas.
 */
class StokOpnameLaporanTest extends StokOpnameTestCase
{
    private function opnameDisetujui(): array
    {
        $kulit = $this->barang('Kulit Dimsum B', 'pcs', [[10, 1000]]);
        $saus = $this->barang('Saus B', 'pcs', [[10, 500]]);
        $o = $this->mulai([$kulit, $saus]);
        $this->hitung($o, $kulit, 6);   // kurang 4
        $this->hitung($o, $saus, 12);   // lebih 2
        $this->rincian($o, $kulit, [['penyebab' => 'basi', 'qty' => 3, 'foto' => $this->foto()], ['penyebab' => 'hilang', 'qty' => 1]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));

        return [$o, $kulit, $saus];
    }

    public function test_berita_acara_sementara_dan_final_pdf_excel(): void
    {
        [$o] = $this->opnameDisetujui();

        // Sementara: pemeriksa boleh, penghitung buta tidak
        $this->actingAs($this->manajer)->get(route('stok-opname.berita-acara', [$o, 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=Berita-Acara-' . $o->nomor . '-SEMENTARA.pdf');
        $this->actingAs($this->kasir)->get(route('stok-opname.berita-acara', [$o, 'format' => 'pdf']))->assertForbidden();

        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');
        $this->actingAs($this->kasir)->get(route('stok-opname.berita-acara', [$o, 'format' => 'pdf']))->assertOk();
        $this->actingAs($this->admin)->get(route('stok-opname.berita-acara', [$o, 'format' => 'excel']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $d = app(\App\Services\StokOpnameService::class)->dataBeritaAcara($o->fresh());
        $this->assertTrue($d['final']);
        $this->assertSame(2, $d['ringkasan']['dihitung']);
        $this->assertEquals(4000, $d['ringkasan']['nilai_kurang']);
        $this->assertEquals(1000, $d['ringkasan']['nilai_lebih']);
        $this->assertEquals(-3000, $d['ringkasan']['nilai_bersih']);
        $this->assertCount(1, $d['foto']);
    }

    public function test_laporan_kerugian_stok_angka_dan_izin(): void
    {
        [$o] = $this->opnameDisetujui();
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o));

        $data = app(\App\Services\LaporanKerugianStokService::class)->hitung(today()->startOfMonth(), today(), [$this->cabang->id]);
        $this->assertEquals(4000, $data['kerugian_kotor']);
        $this->assertEquals(1000, $data['nilai_lebih']);
        $this->assertEquals(3000, $data['kerugian_bersih']);
        $penyebab = $data['per_penyebab']->keyBy('penyebab');
        $this->assertEquals(3000, $penyebab['basi']->nilai);
        $this->assertEquals(1000, $penyebab['hilang']->nilai);
        $this->assertEquals(3000, (float) $data['stok_rusak']->sum('nilai'));

        $this->actingAs($this->manajer)->get(route('laporan.kerugian-stok'))->assertOk()->assertSee('Kulit Dimsum B');
        $this->actingAs($this->manajer)->get(route('laporan.kerugian-stok', ['format' => 'excel']))->assertOk();
        $this->actingAs($this->manajer)->get(route('laporan.kerugian-stok', ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->kasir)->get(route('laporan.kerugian-stok'))->assertForbidden();

        // Manajer cabang lain tidak melihat lokasi ini
        $lain = \App\Models\Cabang::where('tipe', 'cabang')->where('id', '!=', $this->cabang->id)->orderBy('id')->firstOrFail();
        $manajerLain = $this->buatUser('manajer_cabang', $lain->id);
        $this->actingAs($manajerLain)->get(route('laporan.kerugian-stok', ['cabang_id' => $this->cabang->id]))->assertOk()->assertDontSee('Kulit Dimsum B');
    }

    public function test_laba_rugi_formal_per_akun_dan_neraca_seimbang(): void
    {
        [$o] = $this->opnameDisetujui();
        $neraca = app(NeracaService::class);
        $sebelum = $neraca->hitungNeraca(today(), $this->cabang->id);
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o));
        $sesudah = $neraca->hitungNeraca(today(), $this->cabang->id);

        $this->assertEqualsWithDelta($sebelum['selisih'], $sesudah['selisih'], 0.01, 'neraca tidak bergeser');
        $this->assertEqualsWithDelta($sebelum['aset']['total_aset'] - 3000, $sesudah['aset']['total_aset'], 0.01, 'persediaan turun 4.000 − 1.000');

        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(today(), today(), $this->cabang->id);
        $beban = collect($lr['beban_operasional']['detail'])->keyBy('kode');
        $this->assertEquals(3000, $beban['6-1403']['jumlah']);
        $this->assertEquals(1000, $beban['6-1404']['jumlah']);
        $lain = collect($lr['pendapatan_lain']['detail'])->keyBy('kode');
        $this->assertEquals(1000, $lain['7-1103']['jumlah']);
    }

    public function test_tautan_nomor_so_di_kartu_stok_dan_kas(): void
    {
        [$o, $kulit] = $this->opnameDisetujui();
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o));

        $this->actingAs($this->admin)->get(route('stok.kartu', ['item_id' => $kulit->id, 'lokasi_id' => $this->cabang->id]))
            ->assertOk()->assertSee(route('stok-opname.show', $o->id), false)->assertSee($o->nomor);
        $this->actingAs($this->admin)->get(route('keuangan.index', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString(), 'cabang_id' => $this->cabang->id]))
            ->assertOk()->assertSee(route('stok-opname.show', $o->id), false);
    }
}
