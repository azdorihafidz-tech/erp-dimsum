<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\Panduan;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Tooltip;
use App\Services\StokService;
use Database\Seeders\Perbaikan438PanduanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap E+F (CLAUDE.md 4.38-E/F, 2026-10-19) — harga & stok per outlet:
 * daftar Bahan Baku/Produk Jual selalu menyebut lokasi harganya, halaman
 * Harga Outlet (1 outlet per tampilan), salin pengaturan saat tambah cabang,
 * plus audit menu stok (qty minimum per lokasi, harga outlet di adjustment).
 */
class HargaPerOutletTahapFTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private int $seq = 0;

    private function cabang(string $nama, string $tipe = 'cabang'): Cabang
    {
        return Cabang::create(['nama_cabang' => $nama, 'kode_cabang' => 'TF' . (++$this->seq) . rand(100, 999), 'tipe' => $tipe, 'is_active' => true]);
    }

    private function bahan(float $harga = 100, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'TF-' . uniqid(), 'nama_item' => 'Bahan TF ' . uniqid(), 'tipe' => 'bahan_baku',
            'satuan' => 'pcs', 'harga_beli_terakhir' => $harga, 'is_active' => true, 'track_stok' => true, 'qty_minimum' => 5,
        ], $extra));
    }

    private function produk(float $harga = 1200, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'TFP-' . uniqid(), 'nama_item' => 'Dimsum TF ' . uniqid(), 'tipe' => 'produk_jual',
            'satuan' => 'pcs', 'harga_jual' => $harga, 'is_active' => true, 'track_stok' => false,
        ], $extra));
    }

    // ===== Daftar selalu menyebut lokasi harga =====

    public function test_daftar_produk_jual_tampil_harga_outlet_bukan_harga_umum(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = $this->cabang('Outlet TF Satu');
        $lain = $this->cabang('Outlet TF Dua');
        $p = $this->produk(1200, ['nama_item' => 'Dimsum TF Harga']);
        ItemCabang::create(['item_id' => $p->id, 'cabang_id' => $outlet->id, 'harga_override' => 1500, 'is_active' => true]);
        ItemCabang::create(['item_id' => $p->id, 'cabang_id' => $lain->id, 'is_active' => false]);

        $this->actingAs($owner)->get(route('master.produk-jual.index', ['cabang_id' => $outlet->id, 'search' => 'Dimsum TF Harga']))->assertOk()
            ->assertSee('Harga yang tampil adalah harga di')
            ->assertSee('Rp 1.500')
            ->assertSee('Harga umum Rp 1.200')
            ->assertSee(route('master.produk-jual.outlet', [$p, 'cabang_id' => $outlet->id]), false);

        $this->actingAs($owner)->get(route('master.produk-jual.index', ['cabang_id' => $lain->id, 'search' => 'Dimsum TF Harga']))->assertOk()
            ->assertSee('Tidak dijual di Outlet TF Dua');

        $this->actingAs($owner)->get(route('master.produk-jual.index', ['cabang_id' => 0, 'search' => 'Dimsum TF Harga']))->assertOk()
            ->assertSee('Harga Umum')
            ->assertSee('Harga beda di 1 outlet');
    }

    public function test_daftar_bahan_baku_tampil_harga_dan_stok_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = $this->cabang('Outlet TF Bahan');
        $b = $this->bahan(100, ['nama_item' => 'Bahan TF Daftar']);
        ItemCabang::create(['item_id' => $b->id, 'cabang_id' => $outlet->id, 'harga_beli' => 150, 'is_active' => true]);
        Stock::create(['item_id' => $b->id, 'lokasi_id' => $outlet->id, 'qty' => 3, 'qty_minimum' => 10]);

        $this->actingAs($owner)->get(route('master.bahan-baku.index', ['cabang_id' => $outlet->id, 'search' => 'Bahan TF Daftar']))->assertOk()
            ->assertSee('Harga Beli di Outlet TF Bahan')
            ->assertSee('Rp 150')
            ->assertSee('min 10');

        $this->actingAs($owner)->get(route('master.bahan-baku.index', ['cabang_id' => 0, 'search' => 'Bahan TF Daftar']))->assertOk()
            ->assertSee('Harga Standar')
            ->assertSee('beda di 1 outlet');
    }

    public function test_manajer_cabang_hanya_bisa_lihat_dan_atur_outletnya_sendiri(): void
    {
        $milik = $this->cabang('Outlet TF Milik');
        $bukan = $this->cabang('Outlet TF Bukan');
        $manajer = $this->buatUser('manajer_cabang', $milik->id);
        $b = $this->bahan();

        $this->actingAs($manajer)->withSession(['active_cabang_id' => $milik->id])
            ->get(route('master.bahan-baku.index'))->assertOk()->assertSee('Harga Beli di Outlet TF Milik');
        $this->actingAs($manajer)->get(route('master.bahan-baku.index', ['cabang_id' => $bukan->id]))->assertForbidden();
        $this->actingAs($manajer)->get(route('master.bahan-baku.outlet', [$b, 'cabang_id' => $bukan->id]))->assertForbidden();
        $this->actingAs($manajer)->put(route('master.bahan-baku.outlet.update', $b), ['cabang_id' => $bukan->id, 'harga_beli' => 1])->assertForbidden();
        $this->assertFalse(ItemCabang::where('item_id', $b->id)->where('cabang_id', $bukan->id)->exists());
    }

    // ===== Halaman Harga & Stok per Outlet: Bahan Baku =====

    public function test_halaman_outlet_bahan_baku_simpan_harga_dan_minimum_satu_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = $this->cabang('Outlet TF Atur');
        $lain = $this->cabang('Outlet TF Lain');
        $b = $this->bahan(100, ['unit_beli' => 'pack', 'isi_per_unit_beli' => 50]);
        Stock::create(['item_id' => $b->id, 'lokasi_id' => $outlet->id, 'qty' => 12, 'qty_minimum' => 5]);

        $this->actingAs($owner)->get(route('master.bahan-baku.outlet', [$b, 'cabang_id' => $outlet->id]))->assertOk()
            ->assertSee('Anda sedang mengatur untuk:')
            ->assertSee('Outlet TF Atur')
            ->assertSee('id="hargaPack"', false)
            ->assertSee('atau Harga per pack');

        $this->actingAs($owner)->put(route('master.bahan-baku.outlet.update', $b), [
            'cabang_id' => $outlet->id, 'harga_beli' => '166.67', 'qty_minimum' => '20',
        ])->assertRedirect(route('master.bahan-baku.index'));

        $this->assertEqualsWithDelta(166.67, $b->fresh()->hargaBeliDiCabang($outlet->id), 0.001);
        $s = Stock::where('item_id', $b->id)->where('lokasi_id', $outlet->id)->first();
        $this->assertEquals(20, (float) $s->qty_minimum);
        $this->assertEquals(12, (float) $s->qty, 'qty stok tidak boleh berubah');
        $this->assertEquals(100, $b->fresh()->hargaBeliDiCabang($lain->id), 'outlet lain tidak tersentuh');
        $this->assertFalse(Stock::where('item_id', $b->id)->where('lokasi_id', $lain->id)->exists());

        // Dikosongkan → kembali ke Harga Standar
        $this->actingAs($owner)->put(route('master.bahan-baku.outlet.update', $b), ['cabang_id' => $outlet->id, 'harga_beli' => '', 'qty_minimum' => ''])->assertRedirect();
        $this->assertEquals(100, $b->fresh()->hargaBeliDiCabang($outlet->id));
    }

    public function test_form_bahan_baku_tanpa_tabel_banyak_outlet_dan_ada_tombol_harga_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $b = $this->bahan();

        $this->actingAs($owner)->get(route('master.bahan-baku.edit', $b))->assertOk()
            ->assertDontSee('name="harga_cabang[', false)
            ->assertSee('Qty Minimum Awal')
            ->assertSee(route('master.bahan-baku.outlet', $b), false);
    }

    public function test_tambah_bahan_stok_awal_dan_minimum_backend_lama_tetap_jalan(): void
    {
        $owner = $this->buatUser('owner');
        $a = $this->cabang('Outlet TF A');
        $kode = 'TF-NEW-' . uniqid();

        $this->actingAs($owner)->post(route('master.bahan-baku.store'), [
            'kode_item' => $kode, 'nama_item' => 'Bahan Baru TF', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => '1.000', 'qty_minimum' => 4, 'is_active' => 1, 'track_stok' => 1,
            'stok_awal' => [$a->id => 10],
        ])->assertRedirect();

        $item = Item::where('kode_item', strtoupper($kode))->firstOrFail();
        $s = Stock::where('item_id', $item->id)->where('lokasi_id', $a->id)->first();
        $this->assertEquals(10, (float) $s->qty);
        $this->assertEquals(4, (float) $s->qty_minimum, 'pakai Qty Minimum Awal');
    }

    // ===== Halaman Harga per Outlet: Produk Jual =====

    public function test_halaman_outlet_produk_hpp_pakai_harga_bahan_outlet_termasuk_bumbu(): void
    {
        $owner = $this->buatUser('owner');
        $mahal = $this->cabang('Outlet TF Mahal');
        $biasa = $this->cabang('Outlet TF Biasa');
        $bahanA = $this->bahan(100);
        $bahanB = $this->bahan(1000, ['satuan' => 'kg']);
        ItemCabang::create(['item_id' => $bahanA->id, 'cabang_id' => $mahal->id, 'harga_beli' => 150, 'is_active' => true]);
        ItemCabang::create(['item_id' => $bahanB->id, 'cabang_id' => $mahal->id, 'harga_beli' => 2000, 'is_active' => true]);

        $bumbu = ResepBumbu::create(['nama' => 'Bumbu TF', 'kode' => 'BTF-' . uniqid(), 'item_id' => null, 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        ResepBumbuItem::create(['resep_bumbu_id' => $bumbu->id, 'item_id' => $bahanB->id, 'qty_per_unit' => 0.5, 'satuan' => 'kg', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);

        $p = $this->produk(5000);
        $resep = ResepBumbu::create(['nama' => $p->nama_item, 'kode' => 'R-' . uniqid(), 'item_id' => $p->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahanA->id, 'qty_per_unit' => 2, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'resep_bumbu_ref_id' => $bumbu->id, 'qty_per_unit' => 1, 'satuan' => 'porsi', 'is_wajib' => true, 'mode_harga' => 'gratis', 'urutan' => 1]);

        // Mahal: 2×150 + 0.5×2000 = 1300; Biasa: 2×100 + 0.5×1000 = 700
        $this->actingAs($owner)->get(route('master.produk-jual.outlet', [$p, 'cabang_id' => $mahal->id]))->assertOk()
            ->assertSee('Rp 1.300')->assertSee('Outlet TF Mahal');
        $this->actingAs($owner)->get(route('master.produk-jual.outlet', [$p, 'cabang_id' => $biasa->id]))->assertOk()
            ->assertSee('Rp 700');
    }

    public function test_simpan_harga_outlet_produk_dan_simpan_data_produk_tidak_mereset_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = $this->cabang('Outlet TF Jual');
        $p = $this->produk(1200);
        ItemCabang::create(['item_id' => $p->id, 'cabang_id' => $outlet->id, 'is_active' => true]);

        $this->actingAs($owner)->put(route('master.produk-jual.outlet.update', $p), [
            'cabang_id' => $outlet->id, 'harga_jual' => 1500, 'dijual' => 1,
        ])->assertRedirect(route('master.produk-jual.index'));
        $this->assertEquals(1500, $p->fresh('itemCabang')->hargaEfektifDiCabang($outlet->id));

        // Simpan Data Produk (form edit tidak memuat field outlet) → harga outlet tetap
        $this->actingAs($owner)->get(route('master.produk-jual.edit', $p))->assertOk()
            ->assertDontSee('name="cabang_harga[', false)
            ->assertSee(route('master.produk-jual.outlet', $p), false)
            ->assertSee('Harga Jual Umum');
        $this->actingAs($owner)->put(route('master.produk-jual.update', $p), [
            'kode_item' => $p->kode_item, 'nama_item' => 'Dimsum TF Ganti Nama', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => '1.300', 'is_active' => 1,
        ])->assertRedirect();
        $p->refresh()->load('itemCabang');
        $this->assertSame('Dimsum TF Ganti Nama', $p->nama_item);
        $this->assertEquals(1500, $p->hargaEfektifDiCabang($outlet->id));
        $this->assertTrue($p->tersediaDiCabang($outlet->id));

        // Tidak dijual di outlet ini
        $this->actingAs($owner)->put(route('master.produk-jual.outlet.update', $p), ['cabang_id' => $outlet->id, 'harga_jual' => ''])->assertRedirect();
        $p->refresh()->load('itemCabang');
        $this->assertFalse($p->tersediaDiCabang($outlet->id));
        $this->assertEquals(1300, $p->hargaEfektifDiCabang($outlet->id), "tidak dijual → harga outlet tidak berlaku");
    }

    public function test_tambah_produk_centang_outlet_tempat_dijual(): void
    {
        $owner = $this->buatUser('owner');
        $a = $this->cabang('Outlet TF PJ A');
        $b = $this->cabang('Outlet TF PJ B');

        $this->actingAs($owner)->get(route('master.produk-jual.create'))->assertOk()
            ->assertSee('Dijual di Outlet')
            ->assertSee('name="cabang_aktif[]" value="' . $a->id . '"', false)
            ->assertDontSee('name="cabang_harga[', false);

        $kode = 'PJTF-' . uniqid();
        $this->actingAs($owner)->post(route('master.produk-jual.store'), [
            'kode_item' => $kode, 'nama_item' => 'Dimsum TF Baru', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => '20.000', 'is_active' => 1, 'kelola_outlet' => 1, 'cabang_aktif' => [$a->id],
        ])->assertRedirect();

        $item = Item::where('kode_item', strtoupper($kode))->firstOrFail()->load('itemCabang');
        $this->assertTrue($item->tersediaDiCabang($a->id));
        $this->assertFalse($item->tersediaDiCabang($b->id));
        $this->assertEquals(20000, $item->hargaEfektifDiCabang($a->id));
    }

    // ===== Tambah cabang: salin pengaturan =====

    public function test_tambah_cabang_salin_harga_dan_pengaturan_dari_cabang_lain(): void
    {
        $owner = $this->buatUser('owner');
        $sumber = $this->cabang('Outlet TF Sumber');
        $p = $this->produk(1200);
        $tidakDijual = $this->produk(900);
        $b = $this->bahan(100);
        ItemCabang::create(['item_id' => $p->id, 'cabang_id' => $sumber->id, 'harga_override' => 1500, 'is_active' => true]);
        ItemCabang::create(['item_id' => $tidakDijual->id, 'cabang_id' => $sumber->id, 'is_active' => false]);
        ItemCabang::create(['item_id' => $b->id, 'cabang_id' => $sumber->id, 'harga_beli' => 140, 'is_active' => true]);
        Stock::create(['item_id' => $b->id, 'lokasi_id' => $sumber->id, 'qty' => 50, 'qty_minimum' => 25]);

        $this->actingAs($owner)->get(route('cabang.create'))->assertOk()->assertSee('Salin Harga &amp; Pengaturan Barang dari', false);

        $kode = 'TFN' . rand(100, 999);
        $this->actingAs($owner)->post(route('cabang.store'), [
            'kode_cabang' => $kode, 'nama_cabang' => 'Outlet TF Baru Salin', 'tipe' => 'cabang',
            'salin_dari_cabang_id' => $sumber->id,
        ])->assertRedirect(route('cabang.index'));

        $baru = Cabang::where('kode_cabang', strtoupper($kode))->firstOrFail();
        $this->assertEquals(1500, $p->fresh('itemCabang')->hargaEfektifDiCabang($baru->id));
        $this->assertFalse($tidakDijual->fresh('itemCabang')->tersediaDiCabang($baru->id));
        $this->assertEquals(140, $b->fresh()->hargaBeliDiCabang($baru->id));
        $st = Stock::where('item_id', $b->id)->where('lokasi_id', $baru->id)->first();
        $this->assertEquals(25, (float) $st->qty_minimum);
        $this->assertEquals(0, (float) $st->qty, 'stok tidak ikut disalin');
    }

    // ===== Audit menu stok =====

    public function test_kartu_stok_tampilkan_minimum_lokasi(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Kartu');
        $item = $this->bahan(100, ['qty_minimum' => 3]);
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $cab->id, 'qty' => 9, 'qty_minimum' => 11]);

        $this->actingAs($owner)->get(route('stok.kartu', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Outlet TF Kartu')
            ->assertSeeInOrder(['11', 'Minimum (pcs)']);
    }

    public function test_laporan_stok_minimum_abaikan_barang_tanpa_minimum(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Laporan');
        $tanpaMin = $this->bahan(100, ['nama_item' => 'Bahan TF Tanpa Minimum', 'qty_minimum' => 0]);
        $menipis = $this->bahan(100, ['nama_item' => 'Bahan TF Menipis']);
        Stock::create(['item_id' => $tanpaMin->id, 'lokasi_id' => $cab->id, 'qty' => 0, 'qty_minimum' => 0]);
        Stock::create(['item_id' => $menipis->id, 'lokasi_id' => $cab->id, 'qty' => 2, 'qty_minimum' => 10]);

        $this->actingAs($owner)->get(route('laporan.stok.minimum', ['lokasi_id' => $cab->id]))->assertOk()
            ->assertSee('Bahan TF Menipis')
            ->assertDontSee('Bahan TF Tanpa Minimum');
    }

    public function test_adjustment_tambah_stok_tanpa_harga_pakai_harga_outlet(): void
    {
        $owner = $this->buatUser('owner');
        $cab = $this->cabang('Outlet TF Adj');
        $item = $this->bahan(100);
        ItemCabang::create(['item_id' => $item->id, 'cabang_id' => $cab->id, 'harga_beli' => 140, 'is_active' => true]);

        $this->actingAs($owner);
        app(StokService::class)->adjustment($item->id, $cab->id, 10, 'opname test');

        $batch = StockBatch::where('item_id', $item->id)->where('lokasi_id', $cab->id)->latest('id')->first();
        $this->assertEquals(140, (float) $batch->harga_beli_per_unit);

        $this->actingAs($owner)->getJson(route('api.stok.qty', ['item_id' => $item->id, 'lokasi_id' => $cab->id]))
            ->assertOk()->assertJsonPath('harga_beli_terakhir', 140);
    }

    public function test_panduan_dan_tooltip_tahap_f(): void
    {
        $this->seed(Perbaikan438PanduanSeeder::class);
        $this->seed(Perbaikan438PanduanSeeder::class); // idempotent

        $this->assertSame(1, substr_count((string) Panduan::where('slug', 'bahan-baku')->value('konten'), '<!-- PERBAIKAN438-F -->'));
        $this->assertStringContainsString('Harga Outlet', (string) Panduan::where('slug', 'produk-jual')->value('konten'));
        foreach (['master_bahan_baku.pengaturan_outlet', 'master_bahan_baku.qty_minimum_awal', 'master_produk_jual.hpp_outlet', 'master_produk_jual.harga_umum', 'cabang.salin_pengaturan'] as $key) {
            $this->assertTrue(Tooltip::where('key', $key)->exists(), $key);
        }
    }
}
