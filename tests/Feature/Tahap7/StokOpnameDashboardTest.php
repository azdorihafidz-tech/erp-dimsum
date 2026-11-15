<?php

namespace Tests\Feature\Tahap7;

use App\Notifications\StokOpnameNotification;
use App\Services\StokOpnamePengingat;
use App\Services\StokOpnameService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Stok Opname Tahap E — dashboard, pengingat akhir bulan, angka merah di menu. */
class StokOpnameDashboardTest extends StokOpnameTestCase
{
    private function jumlahPengingat(int $userId): int
    {
        return DB::table('notifications')->where('notifiable_id', $userId)->where('type', StokOpnameNotification::class)
            ->where('data', 'like', '%' . StokOpnamePengingat::JUDUL . '%')->count();
    }

    public function test_dashboard_status_per_lokasi_dan_grafik(): void
    {
        $item = $this->barang('Kulit E', 'pcs', [[10, 1000]]);
        $o = $this->mulai([$item]);
        $this->hitung($o, $item, 9);
        $this->rincian($o, $item, [['penyebab' => 'susut', 'qty' => 1]]);
        $this->actingAs($this->manajer)->post(route('stok-opname.ajukan', $o));

        $this->actingAs($this->admin)->get(route('stok-opname.dashboard'))->assertOk()
            ->assertSee('Menunggu persetujuan')->assertSee($o->nomor)->assertSee('Belum');
        $this->actingAs($this->admin)->post(route('stok-opname.setujui', $o));
        $res = $this->actingAs($this->admin)->get(route('stok-opname.dashboard'))->assertOk()->assertSee('grafikAkurasi', false)->assertSee('tabelAkurasi', false);
        $d = app(\App\Services\StokOpnameDashboardService::class)->hitung(collect([$this->cabang]));
        $this->assertSame('sudah', $d['status']->first()->kode);
        $this->assertEquals(1000, $d['kerugian_ini']['kerugian_kotor']);
        $this->assertSame('Kulit E', $d['sering_selisih']->first()->nama_item);

        $this->actingAs($this->kasir)->get(route('stok-opname.dashboard'))->assertOk();
        DB::table('role_permissions')->where('role', 'helper')->whereIn('permission_id', DB::table('permissions')->where('name', 'like', 'stok_opname.%')->pluck('id'))->delete();
        \Illuminate\Support\Facades\Cache::forget('role_permissions_helper');
        $this->actingAs($this->buatUser('helper', $this->cabang->id))->get(route('stok-opname.dashboard'))->assertForbidden();
    }

    public function test_pengingat_masa_satu_kali_per_hari_dan_bisa_dimatikan(): void
    {
        DB::table('pengaturan_umums')->update(['opname_pengingat_aktif' => true, 'opname_pengingat_hari' => 3, 'opname_pengingat_jam' => '08:00']);
        DB::table('cabangs')->where('id', $this->cabang->id)->update(['kepala_cabang_id' => $this->manajer->id]);
        $akhir = now()->endOfMonth()->startOfDay();

        $this->assertFalse(StokOpnamePengingat::dalamMasa($akhir->copy()->subDays(4)->setTime(9, 0)), 'H-4 belum masa');
        $this->assertFalse(StokOpnamePengingat::dalamMasa($akhir->copy()->subDays(3)->setTime(7, 0)), 'sebelum jamnya');
        $kini = $akhir->copy()->subDays(3)->setTime(9, 0);
        $this->travelTo($kini);
        $this->assertTrue(StokOpnamePengingat::dalamMasa($kini));

        $svc = app(StokOpnamePengingat::class);
        $svc->jalankan($kini);
        $this->assertSame(1, $this->jumlahPengingat($this->manajer->id), 'kepala lokasi dapat');
        $svc->jalankan($kini->copy()->addHour());
        $this->assertSame(1, $this->jumlahPengingat($this->manajer->id), 'sekali per hari');
        $this->assertSame(0, $this->jumlahPengingat($this->kasir->id));

        // Lokasi yang sudah mulai opname bulan ini tidak diingatkan lagi
        $item = $this->barang('Garam E', 'pcs', [[5, 100]]);
        $o = $this->mulai([$item]);
        $o->forceFill(['created_at' => $kini])->save();
        $this->assertFalse(StokOpnamePengingat::lokasiBelum(collect([$this->cabang->id]), $kini)->contains('id', $this->cabang->id));

        DB::table('pengaturan_umums')->update(['opname_pengingat_aktif' => false]);
        $this->assertFalse(StokOpnamePengingat::dalamMasa($kini));
        $this->assertSame(0, $svc->jalankan($kini));
        $this->travelBack();
    }

    public function test_tanpa_kepala_lokasi_dikirim_ke_manajer_cabang(): void
    {
        DB::table('cabangs')->where('id', $this->cabang->id)->update(['kepala_cabang_id' => null]);
        $penerima = StokOpnamePengingat::penerima($this->cabang->fresh());
        $this->assertTrue($penerima->contains('id', $this->manajer->id));
        $this->assertFalse($penerima->contains('id', $this->kasir->id));
    }

    public function test_angka_merah_menu_selama_masa_pengingat(): void
    {
        DB::table('pengaturan_umums')->update(['opname_pengingat_aktif' => true, 'opname_pengingat_hari' => 10, 'opname_pengingat_jam' => '00:00']);
        $this->travelTo(now()->endOfMonth()->setTime(10, 0));
        $this->actingAs($this->manajer);
        $this->assertGreaterThanOrEqual(1, StokOpnameService::badgeMenu(), 'lokasi manajer belum opname');
        $this->actingAs($this->kasir);
        $this->assertSame(0, StokOpnameService::badgeMenu(), 'penghitung tanpa opname tidak dapat angka');
        $this->travelBack();
    }
}
