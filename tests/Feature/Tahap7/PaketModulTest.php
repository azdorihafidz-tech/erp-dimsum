<?php

namespace Tests\Feature\Tahap7;

use App\Models\KamusMenu;
use App\Support\Paket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Paket & Langganan Tahap B — modul aktif per instalasi. */
class PaketModulTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function semuaKecuali(string ...$mati): array
    {
        return array_values(array_diff(array_keys(Paket::MODUL), $mati));
    }

    public function test_bawaan_semua_modul_tanpa_batas(): void
    {
        $this->assertSame('Lengkap', Paket::namaPaket());
        $this->assertTrue(Paket::data()['segel_valid']);
        $this->assertSame('tanpa_batas', Paket::statusLangganan()['kode']);
    }

    public function test_modul_mati_hilang_untuk_semua_termasuk_owner_dan_kembali_saat_dinyalakan(): void
    {
        $owner = $this->buatUser('owner');
        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertSee(route('stok-opname.index'), false);

        Paket::simpan($this->semuaKecuali('stok_opname'), null, 7, null);
        $this->assertFalse($owner->hasPermission('stok_opname.view'));
        $this->assertFalse($owner->can('stok_opname.view'));
        $this->assertFalse($admin->hasPermission('stok_opname.setujui'));
        $this->assertTrue($owner->hasPermission('stok.view'), 'modul lain tetap');
        $this->assertTrue($owner->hasPermission('cabang.view'), 'inti tetap');

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertDontSee(route('stok-opname.index'), false);
        $this->actingAs($owner)->get(route('stok-opname.index'))->assertForbidden()->assertSee('tidak termasuk paket');
        $this->actingAs($owner)->getJson(route('stok-opname.index'))->assertForbidden()->assertJson(['ok' => false]);
        $this->actingAs($this->buatUser('teknisi'))->get(route('stok-opname.index'))->assertForbidden();

        // Kamus ikut hilang (Owner pun)
        KamusMenu::semuaAktif(); // pastikan cache tidak menyimpan status paket
        $entri = KamusMenu::where('slug', 'stok-opname')->firstOrFail();
        $this->assertFalse($entri->bolehDilihat($owner));
        $this->assertTrue(KamusMenu::where('slug', 'stok-barang')->firstOrFail()->bolehDilihat($owner));

        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
        $this->assertTrue($admin->hasPermission('stok_opname.setujui'), 'centangan lama kembali');
        $this->actingAs($owner)->get(route('stok-opname.index'))->assertOk();
    }

    public function test_role_hak_akses_menyembunyikan_dan_tidak_menghapus_centangan_modul_mati(): void
    {
        $owner = $this->buatUser('owner');
        $idOpname = DB::table('permissions')->where('name', 'stok_opname.view')->value('id');
        $idStok = DB::table('permissions')->where('name', 'stok.view')->value('id');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $idOpname)->exists());

        Paket::simpan($this->semuaKecuali('stok_opname'), null, 7, null);
        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('stok_opname.view');
        // Simpan centangan kasir hanya dengan izin yang tampil
        $this->actingAs($owner)->post(route('role.update-permissions', 'kasir'), ['permission_ids' => [$idStok]])->assertSessionHas('success');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $idOpname)->exists(), 'centangan tersembunyi tetap');
    }

    public function test_ketergantungan_dan_nama_paket(): void
    {
        $hasil = Paket::simpan(['produksi'], null, 7, null);
        $this->assertEqualsCanonicalizing(['produksi', 'stok'], $hasil['baru']['modul']);
        $this->assertSame('Kustom', Paket::namaPaket());
        Paket::simpan(['penjualan', 'stok'], null, 7, null);
        $this->assertSame('Dasar', Paket::namaPaket());
        Paket::simpan(['akuntansi'], null, 7, null);
        $this->assertTrue(Paket::aktif('keuangan'));
    }

    public function test_halaman_paket_khusus_tim_it_dan_tercatat(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($this->buatUser('owner'))->get(route('paket.index'))->assertForbidden();
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Lengkap');
        $this->actingAs($t)->post(route('paket.update'), ['modul' => ['penjualan', 'stok'], 'masa_tenggang' => 7])->assertSessionHas('success');
        Paket::lupakan();
        $this->assertSame('Dasar', Paket::namaPaket());
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('description', 'like', 'Ubah paket%')->exists());
    }

    public function test_segel_rusak_mode_lunak(): void
    {
        DB::table('lisensi')->update(['modul' => json_encode(['penjualan'])]); // diubah langsung di database
        Paket::lupakan();
        $this->assertFalse(Paket::data()['segel_valid']);
        $this->assertFalse(Paket::bacaSaja(), 'mode lunak: tidak dikunci');
        $this->assertFalse(Paket::aktif('stok'), 'isi yang ada tetap dipakai');

        $t = $this->buatUser('teknisi');
        $this->assertSame(1, min(1, Paket::peringatkanSegelRusak()));
        $this->assertSame(0, Paket::peringatkanSegelRusak(), 'sekali per hari');
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Segel lisensi tidak cocok');

        $this->actingAs($t)->post(route('paket.update'), ['modul' => array_keys(Paket::MODUL), 'masa_tenggang' => 7]);
        Paket::lupakan();
        $this->assertTrue(Paket::data()['segel_valid']);
    }

    public function test_pekerjaan_otomatis_berhenti_saat_modul_mati(): void
    {
        Paket::simpan($this->semuaKecuali('stok_opname'), null, 7, null);
        $this->assertFalse(\App\Services\StokOpnamePengingat::dalamMasa(now()->endOfMonth()->setTime(23, 0)));
    }
}
