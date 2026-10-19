<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Services\StokService;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap E (CLAUDE.md 4.38-E, 2026-10-19) — tab "Pengaturan per Outlet":
 * Bahan Baku (harga pcs/pack + Qty Minimum per lokasi) dan Produk Jual
 * (dijual, harga jual, HPP per outlet), plus audit menu stok.
 */
class PengaturanOutletTahapETest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private int $seq = 0;

    private function cabang(string $nama, string $tipe = 'cabang'): Cabang
    {
        return Cabang::create(['nama_cabang' => $nama, 'kode_cabang' => 'TE' . (++$this->seq) . rand(100, 999), 'tipe' => $tipe, 'is_active' => true]);
    }

    private function bahan(float $harga = 100, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'TE-' . uniqid(), 'nama_item' => 'Bahan TE', 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => $harga, 'is_active' => true, 'track_stok' => true, 'qty_minimum' => 5,
        ], $extra));
    }

    private function payloadBahan(Item $item, array $extra = []): array
    {
        return array_merge([
            'kode_item' => $item->kode_item, 'nama_item' => $item->nama_item, 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => '100', 'qty_minimum' => 5, 'is_active' => 1, 'track_stok' => 1,
        ], $extra);
    }

    public function test_form_bahan_baku_punya_tab_outlet_dengan_harga_pack_dan_minimum(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TE Form');
        $item = $this->bahan(100, ['unit_beli' => 'pack', 'isi_per_unit_beli' => 50]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cab->id, 'qty' => 12, 'qty_minimum' => 7]);

        $this->actingAs($owner)->get(route('master.bahan-baku.edit', $item))->assertOk()
            ->assertSee('Pengaturan per Outlet')
            ->assertSee('id="bahanOutletSection"', false)
            ->assertSee('name="minimum_cabang[' . $cab->id . ']"', false)
            ->assertSee('name="harga_cabang[' . $cab->id . ']"', false)
            ->assertSee('input-harga-pack', false)
            ->assertSee('value="7"', false)
            ->assertSee('Qty Minimum (nilai awal)');

        $this->actingAs($owner)->get(route('master.bahan-baku.create'))->assertOk()
            ->assertSee('name="stok_awal[' . $cab->id . ']"', false)
            ->assertSee('name="minimum_cabang[' . $cab->id . ']"', false);
    }

    public function test_simpan_minimum_per_outlet_tidak_mengubah_qty(): void
    {
        $owner = $this->buatUser('owner');
        $adaStok = $this->cabang('Outlet TE Ada');
        $baru = $this->cabang('Outlet TE Baru');
        $kosong = $this->cabang('Outlet TE Kosong');
        $nol = $this->cabang('Outlet TE Nol');
        $item = $this->bahan();
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $adaStok->id, 'qty' => 12, 'qty_minimum' => 5]);

        $this->actingAs($owner)->put(route('master.bahan-baku.update', $item), $this->payloadBahan($item, [
            'minimum_cabang' => [$adaStok->id => '20', $baru->id => '8', $kosong->id => '', $nol->id => '0'],
        ]))->assertRedirect(route('master.bahan-baku.index'));

        $s = Stock::where('item_id', $item->id)->where('lokasi_id', $adaStok->id)->first();
        $this->assertEquals(20, (float) $s->qty_minimum);
        $this->assertEquals(12, (float) $s->qty, 'qty stok tidak boleh berubah');

        $sBaru = Stock::where('item_id', $item->id)->where('lokasi_id', $baru->id)->first();
        $this->assertNotNull($sBaru);
        $this->assertEquals(8, (float) $sBaru->qty_minimum);
        $this->assertEquals(0, (float) $sBaru->qty);

        $this->assertFalse(Stock::where('item_id', $item->id)->whereIn('lokasi_id', [$kosong->id, $nol->id])->exists());
    }

    public function test_tambah_bahan_stok_awal_pakai_minimum_outlet_atau_nilai_awal(): void
    {
        $owner = $this->buatUser('owner');
        $a = $this->cabang('Outlet TE A');
        $b = $this->cabang('Outlet TE B');
        $kode = 'TE-NEW-' . uniqid();

        $this->actingAs($owner)->post(route('master.bahan-baku.store'), [
            'kode_item' => $kode, 'nama_item' => 'Bahan Baru TE', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => '1.000', 'qty_minimum' => 4, 'is_active' => 1, 'track_stok' => 1,
            'stok_awal' => [$a->id => 10, $b->id => 6],
            'minimum_cabang' => [$a->id => 15, $b->id => ''],
            'harga_cabang' => [$a->id => '166.67'],
        ])->assertRedirect();

        $item = Item::where('kode_item', strtoupper($kode))->firstOrFail();
        $this->assertEquals(15, (float) Stock::where('item_id', $item->id)->where('lokasi_id', $a->id)->value('qty_minimum'));
        $this->assertEquals(4, (float) Stock::where('item_id', $item->id)->where('lokasi_id', $b->id)->value('qty_minimum'));
        $this->assertEquals(10, (float) Stock::where('item_id', $item->id)->where('lokasi_id', $a->id)->value('qty'));
        $this->assertEqualsWithDelta(166.67, $item->hargaBeliDiCabang($a->id), 0.001, 'harga per pcs desimal dari harga pack');
    }

    public function test_hpp_per_outlet_pakai_harga_beli_outlet_termasuk_bumbu_pusat(): void
    {
        $owner = $this->buatUser('owner');
        $mahal = $this->cabang('Outlet TE Mahal');
        $biasa = $this->cabang('Outlet TE Biasa');
        $bahanA = $this->bahan(100);
        $bahanB = $this->bahan(1000, ['satuan' => 'kg']);
        ItemCabang::create(['item_id' => $bahanA->id, 'cabang_id' => $mahal->id, 'harga_beli' => 150, 'is_active' => true]);
        ItemCabang::create(['item_id' => $bahanB->id, 'cabang_id' => $mahal->id, 'harga_beli' => 2000, 'is_active' => true]);

        $bumbu = ResepBumbu::create(['nama' => 'Bumbu TE', 'kode' => 'BTE-' . uniqid(), 'item_id' => null, 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahanB->id, 'qty_per_unit' => 0.5, 'satuan' => 'kg', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $res = $this->actingAs($owner)->postJson(route('master.produk-jual.hpp-outlet'), [
            'resep' => [
                ['item_id' => $bahanA->id, 'qty_per_unit' => 2, 'satuan' => 'pcs'],
                ['resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1],
            ],
        ])->assertOk();

        // Mahal: 2×150 + 0.5×2000 = 1300; Biasa: 2×100 + 0.5×1000 = 700
        $this->assertEqualsWithDelta(1300, (float) $res->json('hpp.' . $mahal->id), 0.01);
        $this->assertEqualsWithDelta(700, (float) $res->json('hpp.' . $biasa->id), 0.01);

        $this->actingAs($this->buatUser('kasir'))->postJson(route('master.produk-jual.hpp-outlet'), ['resep' => []])->assertForbidden();
    }

    public function test_form_produk_jual_tab_outlet_dan_simpan_dijual_harga_tetap_jalan(): void
    {
        $owner = $this->buatUser('owner');
        $a = $this->cabang('Outlet TE PJ A');
        $b = $this->cabang('Outlet TE PJ B');

        $this->actingAs($owner)->get(route('master.produk-jual.create'))->assertOk()
            ->assertSee('id="produkOutletSection"', false)
            ->assertSee('Pengaturan per Outlet')
            ->assertSee('name="cabang_harga[' . $a->id . ']"', false)
            ->assertSee('hppOutlet', false)
            ->assertSee(json_encode(route('master.produk-jual.hpp-outlet')), false);

        $kode = 'PJTE-' . uniqid();
        $this->actingAs($owner)->post(route('master.produk-jual.store'), [
            'kode_item' => $kode, 'nama_item' => 'Dimsum TE', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => '20.000', 'is_active' => 1,
            'cabang_aktif' => [$a->id], 'cabang_harga' => [$a->id => 25000, $b->id => ''],
        ])->assertRedirect();

        $item = Item::where('kode_item', strtoupper($kode))->firstOrFail();
        $this->assertTrue((bool) ItemCabang::where('item_id', $item->id)->where('cabang_id', $a->id)->value('is_active'));
        $this->assertFalse((bool) ItemCabang::where('item_id', $item->id)->where('cabang_id', $b->id)->value('is_active'));
        $this->assertEquals(25000, $item->fresh('itemCabang')->hargaEfektifDiCabang($a->id));

        $this->actingAs($owner)->get(route('master.produk-jual.edit', $item))->assertOk()->assertSee('value="25000"', false);
    }

    public function test_kartu_stok_tampilkan_minimum_lokasi(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TE Kartu');
        $item = $this->bahan(100, ['qty_minimum' => 3]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cab->id, 'qty' => 9, 'qty_minimum' => 11]);

        $this->actingAs($owner)->get(route('stok.kartu', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Outlet TE Kartu')
            ->assertSee('Minimum (pcs)')
            ->assertSeeInOrder(['11', 'Minimum (pcs)']);
    }

    public function test_laporan_stok_minimum_abaikan_barang_tanpa_minimum(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TE Laporan');
        $tanpaMin = $this->bahan(100, ['nama_item' => 'Bahan TE Tanpa Minimum', 'qty_minimum' => 0]);
        $menipis = $this->bahan(100, ['nama_item' => 'Bahan TE Menipis']);
        Stock::create(['item_id' => $tanpaMin->id, 'lokasi_id' => $cab->id, 'qty' => 0, 'qty_minimum' => 0]);
        Stock::create(['item_id' => $menipis->id, 'lokasi_id' => $cab->id, 'qty' => 2, 'qty_minimum' => 10]);

        $this->actingAs($owner)->get(route('laporan.stok.minimum', ['lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Bahan TE Menipis')
            ->assertDontSee('Bahan TE Tanpa Minimum');
    }

    public function test_adjustment_tambah_stok_tanpa_harga_pakai_harga_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TE Adj');
        $item = $this->bahan(100);
        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $cab->id, 'harga_beli' => 140, 'is_active' => true]);

        $this->actingAs($owner);
        app(StokService::class)->adjustment($item->id, $cab->id, 10, 'opname test');

        $batch = \App\Models\StockBatch::where('item_id', $item->id)->where('lokasi_id', $cab->id)->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(140, (float) $batch->harga_beli_per_unit);

        $this->actingAs($owner)->getJson(route('api.stok.qty', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))
            ->assertOk()->assertJsonPath('harga_beli_terakhir', 140);
    }

    public function test_panduan_dan_tooltip_tahap_e(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $this->seed(Perbaikan438PanduanSeeder::class); // idempotent

        $konten = (string) \App\Models\Panduan::where('slug', 'bahan-baku')->value('konten');
        $this->assertSame(1, substr_count($konten, '<!-- PERBAIKAN438-E -->'));
        $this->assertStringContainsString('Pengaturan per Outlet', (string) \App\Models\Panduan::where('slug', 'produk-jual')->value('konten'));
        foreach (['master_bahan_baku.pengaturan_outlet', 'master_bahan_baku.qty_minimum_awal', 'master_produk_jual.hpp_outlet'] as $key) {
            $this->assertTrue(\App\Models\Tooltip::where('key', $key)->exists(), $key);
        }
    }
}
