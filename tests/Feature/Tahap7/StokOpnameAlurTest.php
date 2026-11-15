<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StokOpname;
use App\Models\StokRusak;
use App\Models\TransaksiKeuangan;
use App\Services\StokService;
use Illuminate\Support\Facades\DB;

/**
 * Stok Opname Tahap A (RANCANGAN_STOK_OPNAME.md): alur mulai → hitung → rincian
 * → ajukan → setujui/tolak/batal, angka uang per penyebab, hak akses, perbaikan
 * Penyesuaian Stok.
 */
class StokOpnameAlurTest extends StokOpnameTestCase
{
    // ---------------------------------------------------------------

    public function test_alur_lengkap_nilai_fifo_tanpa_kas_dan_stok_rusak(): void
    {
        // 2 batch: 4 @1.000 (lama) + 6 @2.000 → FIFO kurang 5 = 4×1.000 + 1×2.000 = 6.000
        $kulit = $this->barang('Kulit Dimsum', 'pcs', [[4, 1000], [6, 2000]]);
        $kasSebelum = (float) Kas::where('cabang_id', $this->cabang->id)->sum('saldo_sekarang');
        $o = $this->mulai([$kulit]);
        $this->assertSame('menghitung', $o->status);
        $this->assertMatchesRegularExpression('/^SO-\d{6}-\d{4}$/', $o->nomor);

        // Hitung buta: penghitung tidak menerima stok sistem
        $res = $this->hitung($o, $kulit, 5)->assertOk();
        $this->assertNull($res->json('qty_sistem'));
        $this->assertNull($res->json('selisih'));
        $this->actingAs($this->kasir)->get(route('stok-opname.show', $o))->assertOk()->assertDontSee('Sistem saat dihitung');

        // Pemeriksa melihat selisih
        $this->actingAs($this->manajer)->get(route('stok-opname.show', $o))->assertOk()->assertSee('Sistem saat dihitung');

        $this->rincian($o, $kulit, [
            ['penyebab' => 'rusak', 'qty' => 3, 'foto' => $this->foto()],
            ['penyebab' => 'hilang', 'qty' => 1],
            ['penyebab' => 'sampel', 'qty' => 1],
        ])->assertSessionHas('success');

        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');

        $o->refresh();
        $this->assertSame('disetujui', $o->status);
        $this->assertEquals(5.0, $this->stok($kulit));
        // FIFO berurutan: rusak 3 @1.000, hilang 1 @1.000, sampel 1 @2.000
        $byKode = TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)
            ->join('kategori_transaksis as k', 'k.id', '=', 'transaksi_keuangans.kategori_id')->pluck('transaksi_keuangans.jumlah', 'k.kode');
        $this->assertEquals(3000, (float) $byKode['OPN-RUSAK']);
        $this->assertEquals(1000, (float) $byKode['OPN-HILANG']);
        $this->assertEquals(2000, (float) $byKode['OPN-SAMPEL']);
        $this->assertSame(0, TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->whereNotNull('kas_id')->count());
        $this->assertEquals($kasSebelum, (float) Kas::where('cabang_id', $this->cabang->id)->sum('saldo_sekarang'), 'kas tidak berubah');
        $this->assertEquals(-6000, (float) $o->items()->first()->nilai_selisih);

        $rusak = StokRusak::where('item_id', $kulit->id)->firstOrFail();
        $this->assertEquals(3, (float) $rusak->qty_sisa);
        $this->assertEquals(1000, (float) $rusak->harga_pokok);
        $this->assertSame(3, StockMovement::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->count());
        $this->assertEquals(0, (float) $o->akurasi);
    }

    public function test_penjualan_sesudah_dihitung_tidak_terhapus(): void
    {
        $item = $this->barang('Isian Ayam', 'pcs', [[100, 500]]);
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 95)->assertOk();             // selisih −5 terhadap sistem 100
        $this->actingAs($this->kasir);
        app(StokService::class)->keluar($item->id, $this->cabang->id, 20, 'POS sesudah dihitung');  // stok 80
        $this->rincian($o, $item, [['penyebab' => 'susut', 'qty' => 5]])->assertSessionHas('success');
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');

        $this->assertEquals(75.0, $this->stok($item), '80 − 5, bukan 95');
    }

    public function test_stok_habis_saat_disetujui_ditolak_utuh(): void
    {
        $a = $this->barang('Saus A', 'pcs', [[10, 1000]]);
        $b = $this->barang('Saus B', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$a, $b]);
        $this->hitung($o, $a, 9);
        $this->hitung($o, $b, 4); // kurang 6
        $this->rincian($o, $a, [['penyebab' => 'susut', 'qty' => 1]]);
        $this->rincian($o, $b, [['penyebab' => 'salah_catat', 'qty' => 6]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o))->assertSessionHas('success');
        $this->actingAs($this->kasir);
        app(StokService::class)->keluar($b->id, $this->cabang->id, 7, 'terjual');  // sisa 3 < 6

        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('error');
        $this->assertSame('diajukan', $o->fresh()->status);
        $this->assertEquals(10.0, $this->stok($a), 'barang lain tidak ikut berubah');
        $this->assertSame(0, TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->count());
    }

    public function test_satuan_desimal_dan_bulat(): void
    {
        $kg = $this->barang('Daging Ayam', 'Kg', [[5, 40000]]);
        $pcs = $this->barang('Sumpit', 'pcs', [[50, 100]]);
        $o = $this->mulai([$kg, $pcs]);
        $this->hitung($o, $kg, '4,75')->assertOk()->assertJson(['qty_fisik' => 4.75]);
        $this->hitung($o, $pcs, '12.5')->assertStatus(422);
        $this->hitung($o, $pcs, 48)->assertOk();
    }

    public function test_lebih_masuk_batch_rata_rata_dan_pendapatan_lain(): void
    {
        $item = $this->barang('Plastik', 'pcs', [[10, 100], [10, 300]]); // rata-rata 200
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 23)->assertOk(); // lebih 3
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');

        $this->assertEquals(23.0, $this->stok($item));
        $batch = StockBatch::where('item_id', $item->id)->where('referensi_type', 'stok_opname')->firstOrFail();
        $this->assertEquals(3, (float) $batch->qty_sisa);
        $this->assertEquals(200, (float) $batch->harga_beli_per_unit);
        $tk = TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->firstOrFail();
        $this->assertSame('pemasukan', $tk->tipe->value);
        $this->assertEquals(600, (float) $tk->jumlah);
        $this->assertSame('7-1103', DB::table('kategori_transaksis')->where('id', $tk->kategori_id)->value('kode_akun_coa'));
    }

    public function test_tolak_lalu_diperbaiki_dan_diajukan_ulang(): void
    {
        $item = $this->barang('Kecap', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 8);
        $this->rincian($o, $item, [['penyebab' => 'hilang', 'qty' => 2]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));
        $this->actingAs($this->admin)->post(route('stok-opname.tolak', $o), ['alasan' => 'Hitung ulang rak belakang'])->assertSessionHas('success');
        $o->refresh();
        $this->assertSame('menghitung', $o->status);
        $this->assertTrue($o->ditolak());

        // Hitung ulang → selisih berubah → rincian lama hilang
        $this->actingAs($this->manajer)->post(route('stok-opname.hitung-ulang', $o), ['item_id' => $item->id]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o))->assertSessionHas('error');
        $this->hitung($o, $item, 9)->assertOk();
        $this->assertSame(0, $o->items()->first()->rincian()->count());
        $this->rincian($o, $item, [['penyebab' => 'hilang', 'qty' => 1]])->assertSessionHas('success');
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');
        $this->assertEquals(9.0, $this->stok($item));
    }

    public function test_batal_tidak_mengubah_stok_dan_yang_disetujui_tidak_bisa_dibatalkan(): void
    {
        $item = $this->barang('Minyak', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 3);
        $this->actingAs($this->manajer)->post(route('stok-opname.batal', $o), ['alasan' => 'Salah lokasi'])->assertRedirect(route('stok-opname.index'));
        $this->assertSame('dibatalkan', $o->fresh()->status);
        $this->assertEquals(10.0, $this->stok($item));

        $o2 = $this->mulai([$item]); // barang bebas lagi setelah batal
        $this->hitung($o2, $item, 10);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o2));
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o2));
        $this->assertSame('disetujui', $o2->fresh()->status);
        $this->actingAs($this->manajer)->post(route('stok-opname.batal', $o2), ['alasan' => 'x'])->assertSessionHas('error');
        $this->assertEquals(100.0, (float) $o2->fresh()->akurasi);
    }

    public function test_dua_opname_bertabrakan_ditolak(): void
    {
        $item = $this->barang('Gula', 'pcs', [[10, 1000]]);
        $this->mulai([$item]);
        $this->actingAs($this->manajer)->post(route('stok-opname.store'), [
            'cabang_id' => $this->cabang->id, 'cakupan' => 'barang', 'cakupan_ids' => [$item->id],
        ])->assertSessionHas('error');
        $this->assertSame(1, StokOpname::whereHas('items', fn ($q) => $q->where('item_id', $item->id))->count());
    }

    public function test_tanpa_izin_403_dan_lokasi_lain_403(): void
    {
        $item = $this->barang('Garam', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);

        // Kasir tidak punya izin memulai / setujui
        $this->actingAs($this->kasir)->get(route('stok-opname.create'))->assertForbidden();
        $this->actingAs($this->kasir)->post(route('stok-opname.setujui', $o))->assertForbidden();
        // Penghitung bukan dari lokasi ini
        $lain = Cabang::where('tipe', 'cabang')->where('id', '!=', $this->cabang->id)->orderBy('id')->firstOrFail();
        $kasirLain = $this->buatUser('kasir', $lain->id);
        $this->actingAs($kasirLain)->get(route('stok-opname.show', $o))->assertForbidden();
        $this->actingAs($kasirLain)->postJson(route('stok-opname.hitung', $o), ['item_id' => $item->id, 'qty' => 1])->assertForbidden();
        // Role tanpa izin stok opname sama sekali
        DB::table('role_permissions')->where('role', 'helper')->whereIn('permission_id', DB::table('permissions')->where('name', 'like', 'stok_opname.%')->pluck('id'))->delete();
        \Illuminate\Support\Facades\Cache::forget('role_permissions_helper');
        $this->actingAs($this->buatUser('helper', $this->cabang->id))->get(route('stok-opname.index'))->assertForbidden();
    }

    public function test_penghitung_tidak_boleh_setujui_sendiri_kecuali_owner(): void
    {
        $item = $this->barang('Tepung', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item], $this->admin);
        $this->hitung($o, $item, 10, $this->admin)->assertOk();
        $this->actingAs($this->admin)->post(route('stok-opname.ajukan', $o))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('error');
        $this->assertSame('diajukan', $o->fresh()->status);

        $owner = $this->buatUser('owner');
        $this->hitungOleh($o, $owner);
        $this->actingAs($owner)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');
    }

    public function test_foto_wajib_rusak_dan_hilang_di_atas_batas(): void
    {
        $murah = $this->barang('Tisu', 'pcs', [[10, 1000]]);
        $mahal = $this->barang('Udang', 'pcs', [[10, 100000]]);
        $o = $this->mulai([$murah, $mahal]);
        $this->hitung($o, $murah, 8);
        $this->hitung($o, $mahal, 7);

        $this->rincian($o, $murah, [['penyebab' => 'rusak', 'qty' => 2]])->assertSessionHas('error');
        $this->rincian($o, $murah, [['penyebab' => 'hilang', 'qty' => 2]])->assertSessionHas('success'); // Rp 2.000 < batas
        $this->rincian($o, $mahal, [['penyebab' => 'hilang', 'qty' => 3]])->assertSessionHas('error');  // Rp 300.000 ≥ batas
        $this->rincian($o, $mahal, [['penyebab' => 'hilang', 'qty' => 3, 'foto' => $this->foto()]])->assertSessionHas('success');
        // Jumlah rincian harus = selisih
        $this->rincian($o, $mahal, [['penyebab' => 'susut', 'qty' => 1]])->assertSessionHas('error');
    }

    public function test_closing_hari_ini_menolak_persetujuan(): void
    {
        $item = $this->barang('Cabai', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 9);
        $this->rincian($o, $item, [['penyebab' => 'susut', 'qty' => 1]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));
        DB::table('closings')->insert([
            'jenis' => 'harian', 'cabang_id' => $this->cabang->id, 'periode_mulai' => today(), 'periode_selesai' => today(),
            'label_periode' => 'hari ini', 'versi' => 1, 'status' => 'final', 'omzet' => 0, 'hpp' => 0, 'laba_bersih' => 0,
            'saldo_kas_akhir' => 0, 'nilai_stok' => 0, 'data' => '{}', 'ditutup_oleh' => $this->admin->id, 'ditutup_pada' => now(),
        ]);
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o))->assertSessionHas('error', fn ($m) => str_contains($m, 'Closing'));
        $this->assertSame('diajukan', $o->fresh()->status);
        $this->assertEquals(10.0, $this->stok($item));
    }

    public function test_scan_kode_menambah_satu(): void
    {
        $item = $this->barang('Garpu', 'pcs', [[10, 100]]);
        $o = $this->mulai([$item]);
        $this->actingAs($this->kasir)->postJson(route('stok-opname.scan', $o), ['kode' => strtolower($item->kode_item)])->assertOk()->assertJson(['qty_fisik' => 1]);
        $this->actingAs($this->kasir)->postJson(route('stok-opname.scan', $o), ['kode' => $item->kode_item])->assertOk()->assertJson(['qty_fisik' => 2]);
        $this->actingAs($this->kasir)->postJson(route('stok-opname.scan', $o), ['kode' => 'TIDAK-ADA'])->assertStatus(422);
    }

    public function test_penyesuaian_stok_dikunci_dan_salah_hitung_turun_jadi_selisih_persediaan(): void
    {
        $item = $this->barang('Daun Bawang', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);
        $this->actingAs($this->manajer);
        try {
            app(StokService::class)->adjustment($item->id, $this->cabang->id, 5, 'x', null, 'rusak');
            $this->fail('harus ditolak');
        } catch (\Exception $e) {
            $this->assertStringContainsString($o->nomor, $e->getMessage());
        }
        $this->actingAs($this->manajer)->post(route('stok-opname.batal', $o), ['alasan' => 'uji']);

        app(StokService::class)->adjustment($item->id, $this->cabang->id, 7, 'audit', null, 'salah_hitung');
        $tk = TransaksiKeuangan::where('referensi_type', 'stock_movement')->latest('id')->firstOrFail();
        $this->assertSame('OPN-SELISIH', DB::table('kategori_transaksis')->where('id', $tk->kategori_id)->value('kode'));
        $this->assertEquals(3000, (float) $tk->jumlah);

        $jumlahTk = TransaksiKeuangan::count();
        app(StokService::class)->adjustment($item->id, $this->cabang->id, 9, 'audit naik', null, 'audit');
        $this->assertSame($jumlahTk, TransaksiKeuangan::count(), 'kenaikan audit tetap tidak dicatat');
        $this->assertEquals(9.0, $this->stok($item));
    }

    public function test_halaman_tampil_tanpa_error(): void
    {
        $item = $this->barang('Wortel', 'pcs', [[10, 100]]);
        $this->actingAs($this->manajer)->get(route('stok-opname.index'))->assertOk()->assertSee('Mulai Opname');
        $this->actingAs($this->manajer)->get(route('stok-opname.create'))->assertOk()->assertSee('Cakupan');
        $o = $this->mulai([$item]);
        $this->actingAs($this->kasir)->get(route('stok-opname.index'))->assertOk()->assertDontSee(route('stok-opname.create'));
        $this->actingAs($this->admin)->get(route('stok-opname.show', $o))->assertOk();
        $this->actingAs($this->kasir)->followingRedirects()->get(route('dashboard'))->assertOk();
    }
}
