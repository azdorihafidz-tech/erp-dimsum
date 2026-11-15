<?php

namespace Tests\Feature\Tahap7;

use App\Models\Pelanggan;
use App\Support\Paket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Paket & Langganan Tahap C — masa aktif, masa tenggang, mode baca-saja. */
class PaketLanggananTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function berakhir(Carbon $tanggal, int $tenggang = 7): void
    {
        Paket::simpan(array_keys(Paket::MODUL), $tanggal->toDateString(), $tenggang, null);
    }

    public function test_status_langganan_bertahap(): void
    {
        $sampai = Carbon::parse('2027-01-31');
        $this->berakhir($sampai);
        $this->assertSame('aktif', Paket::statusLangganan(Carbon::parse('2027-01-01'))['kode']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-17'))['kode']);
        $this->assertSame(14, Paket::statusLangganan(Carbon::parse('2027-01-17'))['sisa_hari']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-31 23:00'))['kode'], 'hari terakhir masih berlaku');
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode']);
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-07'))['kode']);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-08'))['kode']);
        $this->berakhir($sampai, 0);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode'], 'tanpa masa tenggang');
    }

    public function test_pita_peringatan_per_status(): void
    {
        $owner = $this->buatUser('owner');
        $kasir = $this->buatUser('kasir', \App\Models\Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'));

        $this->berakhir(today()->addDays(10));
        $this->actingAs($owner)->get(route('kamus-menu.index'))->assertSee('Langganan aplikasi berakhir');
        $this->actingAs($kasir)->get(route('kamus-menu.index'))->assertDontSee('Langganan aplikasi berakhir');

        $this->berakhir(today()->subDays(2));
        $this->actingAs($kasir)->get(route('kamus-menu.index'))->assertSee('Masa tenggang sampai');

        $this->berakhir(today()->subDays(30));
        $this->actingAs($kasir)->get(route('kamus-menu.index'))->assertSee('Mode baca-saja');
    }

    public function test_baca_saja_menolak_simpan_tapi_boleh_melihat_dan_unduh(): void
    {
        $owner = $this->buatUser('owner');
        $this->berakhir(today()->subDays(30));
        $nama = 'Pelanggan Baca Saja ' . uniqid();

        $this->actingAs($owner)->post(route('pelanggan.store'), ['nama_pelanggan' => $nama, 'kode_pelanggan' => 'PLG-T' . rand(100000, 999999), 'telepon' => '0812' . rand(1000000, 9999999)])
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'baca-saja'));
        $this->assertFalse(Pelanggan::where('nama_pelanggan', $nama)->exists());
        $this->actingAs($owner)->postJson(route('pelanggan.store'), ['nama_pelanggan' => $nama])->assertStatus(423)->assertJson(['ok' => false]);

        $this->actingAs($owner)->get(route('pelanggan.index'))->assertOk();
        $this->actingAs($owner)->get(route('laporan.kerugian-stok', ['format' => 'excel']))->assertOk();
        $this->actingAs($owner)->post(route('notifikasi.read-all'))->assertSessionMissing('error');
        $this->actingAs($owner)->post(route('logout'))->assertRedirect();
    }

    public function test_tim_it_tidak_terkena_dan_masa_tenggang_masih_normal(): void
    {
        $this->berakhir(today()->subDays(30));
        $t = $this->buatUser('teknisi');
        $nama = 'Pelanggan Teknisi ' . uniqid();
        $this->actingAs($t)->post(route('pelanggan.store'), ['nama_pelanggan' => $nama, 'kode_pelanggan' => 'PLG-T' . rand(100000, 999999), 'telepon' => '0813' . rand(1000000, 9999999)]);
        $this->assertTrue(Pelanggan::where('nama_pelanggan', $nama)->exists(), 'Tim IT tetap bisa menyimpan');
        // Perpanjang → normal lagi
        $this->actingAs($t)->post(route('paket.update'), ['modul' => array_keys(Paket::MODUL), 'aktif_sampai' => today()->addYear()->toDateString(), 'masa_tenggang' => 7])->assertSessionHas('success');
        Paket::lupakan();
        $this->assertFalse(Paket::bacaSaja());

        $this->berakhir(today()->subDays(2)); // masih tenggang
        $owner = $this->buatUser('owner');
        $nama2 = 'Pelanggan Tenggang ' . uniqid();
        $this->actingAs($owner)->post(route('pelanggan.store'), ['nama_pelanggan' => $nama2, 'kode_pelanggan' => 'PLG-U' . rand(100000, 999999), 'telepon' => '0814' . rand(1000000, 9999999)]);
        $this->assertTrue(Pelanggan::where('nama_pelanggan', $nama2)->exists(), 'masa tenggang: masih normal');
    }

    public function test_pengingat_otomatis_berhenti_saat_baca_saja(): void
    {
        $this->berakhir(today()->subDays(30));
        $this->assertFalse(\App\Services\StokOpnamePengingat::dalamMasa(now()->endOfMonth()->setTime(23, 0)));
    }
}
