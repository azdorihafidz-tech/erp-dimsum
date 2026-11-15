<?php

namespace Tests\Feature\Tahap7;

use App\Models\Item;
use App\Models\Kas;
use App\Models\StockBatch;
use App\Models\StokRusak;
use App\Models\StokRusakTindakan;
use App\Models\TransaksiKeuangan;
use App\Services\LabaRugiFormalService;
use App\Services\LaporanKerugianStokService;
use Illuminate\Support\Facades\DB;

/** Stok Opname Tahap C — tindak lanjut stok rusak. */
class StokOpnameStokRusakTest extends StokOpnameTestCase
{
    private Item $kulit;
    private Kas $kas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kulit = $this->barang('Kulit Rusak C', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$this->kulit]);
        $this->hitung($o, $this->kulit, 5); // kurang 5
        $this->rincian($o, $this->kulit, [
            ['penyebab' => 'rusak', 'qty' => 3, 'foto' => $this->foto()],
            ['penyebab' => 'basi', 'qty' => 2, 'foto' => $this->foto()],
        ]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');
        $this->kas = Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => 'Kas Uji C ' . uniqid(), 'tipe_kas' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 100000, 'is_active' => true]);
    }

    private function rusak(string $penyebab): StokRusak
    {
        return StokRusak::where('item_id', $this->kulit->id)->where('penyebab', $penyebab)->firstOrFail();
    }

    private function tindak(StokRusak $s, array $data, $user = null)
    {
        return $this->actingAs($user ?? $this->manajer)->post(route('stok-rusak.tindak', $s), $data);
    }

    public function test_pakai_lagi_membatalkan_kerugian_dan_hanya_untuk_rusak(): void
    {
        $this->tindak($this->rusak('basi'), ['jenis' => 'pakai_lagi', 'qty' => 1])->assertSessionHas('error');

        $this->tindak($this->rusak('rusak'), ['jenis' => 'pakai_lagi', 'qty' => 1])->assertSessionHas('success');
        $this->assertEquals(6.0, $this->stok($this->kulit));
        $this->assertEquals(2, (float) $this->rusak('rusak')->qty_sisa);
        $this->assertTrue(StockBatch::where('item_id', $this->kulit->id)->where('qty_sisa', 1)->where('harga_beli_per_unit', 1000)->exists());

        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(today(), today(), $this->cabang->id);
        $this->assertEquals(2000, collect($lr['beban_operasional']['detail'])->keyBy('kode')['6-1402']['jumlah'], '3.000 − 1.000');
        $data = app(LaporanKerugianStokService::class)->hitung(today(), today(), [$this->cabang->id]);
        $this->assertEquals(1000, $data['dibatalkan']);
        $this->assertEquals(4000, $data['kerugian_bersih']);
    }

    public function test_dibayar_uang_dan_potong_tagihan_masuk_kas(): void
    {
        $this->tindak($this->rusak('basi'), ['jenis' => 'bayar_uang', 'qty' => 1, 'uang' => '1.500', 'kas_id' => $this->kas->id])->assertSessionHas('success');
        $this->assertEquals(101500, (float) $this->kas->fresh()->saldo_sekarang);
        $tk = TransaksiKeuangan::where('kas_id', $this->kas->id)->latest('id')->firstOrFail();
        $this->assertSame('OPN-PULIH', DB::table('kategori_transaksis')->where('id', $tk->kategori_id)->value('kode'));
        $this->assertSame('pemasukan', $tk->tipe->value);

        $this->tindak($this->rusak('basi'), ['jenis' => 'potong_tagihan', 'qty' => 1, 'uang' => 1000, 'kas_id' => $this->kas->id])->assertSessionHas('error');
        $this->tindak($this->rusak('basi'), ['jenis' => 'potong_tagihan', 'qty' => 1, 'uang' => 1000, 'kas_id' => $this->kas->id, 'nomor_po' => 'PO-001'])->assertSessionHas('success');
        $this->assertEquals(102500, (float) $this->kas->fresh()->saldo_sekarang);
        $this->assertSame('selesai', $this->rusak('basi')->status);

        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(today(), today(), $this->cabang->id);
        $this->assertEquals(2500, collect($lr['pendapatan_lain']['detail'])->keyBy('kode')['7-1104']['jumlah']);
    }

    public function test_musnahkan_wajib_foto_dan_berita_acara(): void
    {
        $s = $this->rusak('rusak');
        $this->tindak($s, ['jenis' => 'musnahkan', 'qty' => 3])->assertSessionHas('error');
        $jumlahTk = TransaksiKeuangan::count();
        $this->tindak($s, ['jenis' => 'musnahkan', 'qty' => 3, 'foto' => $this->foto(), 'saksi' => 'Budi'])->assertSessionHas('success');
        $this->assertSame($jumlahTk, TransaksiKeuangan::count(), 'tidak ada keuangan baru');
        $t = StokRusakTindakan::where('stok_rusak_id', $s->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^BA-\d{6}-\d{4}$/', $t->nomor_ba);
        $this->assertSame('selesai', $s->fresh()->status);
        $this->actingAs($this->manajer)->get(route('stok-rusak.berita-acara', $t))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertEquals(5.0, $this->stok($this->kulit), 'stok jual tidak berubah');
    }

    public function test_jumlah_melebihi_sisa_dan_izin(): void
    {
        $this->tindak($this->rusak('rusak'), ['jenis' => 'musnahkan', 'qty' => 4, 'foto' => $this->foto()])->assertSessionHas('error');
        $this->tindak($this->rusak('rusak'), ['jenis' => 'musnahkan', 'qty' => 1, 'foto' => $this->foto()], $this->kasir)->assertForbidden();
        $this->actingAs($this->kasir)->get(route('stok-rusak.index'))->assertOk()->assertDontSee(route('stok-rusak.tindak', $this->rusak('rusak')));
        $this->actingAs($this->manajer)->get(route('stok-rusak.index'))->assertOk()->assertSee('Kulit Rusak C')->assertSee(route('stok-rusak.tindak', $this->rusak('rusak')));
        $lain = \App\Models\Cabang::where('tipe', 'cabang')->where('id', '!=', $this->cabang->id)->orderBy('id')->firstOrFail();
        $this->tindak($this->rusak('rusak'), ['jenis' => 'musnahkan', 'qty' => 1, 'foto' => $this->foto()], $this->buatUser('manajer_cabang', $lain->id))->assertForbidden();
    }
}
