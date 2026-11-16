<?php

namespace Tests\Feature\Tahap7;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Paket & Langganan Tahap A — akun Tim IT (Teknisi) & Aktivitas Tim IT. */
class TimItTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_teknisi_akses_penuh_termasuk_halaman_khusus_owner(): void
    {
        $t = $this->buatUser('teknisi');
        $this->assertTrue($t->aksesPenuh());
        $this->assertTrue($t->hasPermission('stok_opname.setujui'));
        $this->assertTrue($t->can('cabang.delete'));
        $this->assertTrue($t->canAccessAllBranches());
        $this->actingAs($t)->get(route('role.index'))->assertOk();
        $this->actingAs($t)->get(route('pengaturan.umum'))->assertOk();
        $this->actingAs($t)->get(route('tim-it.index'))->assertOk();
    }

    public function test_teknisi_tersembunyi_dari_pelanggan(): void
    {
        $t = $this->buatUser('teknisi');
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('user.index'))->assertOk()->assertDontSee($t->email);
        $this->actingAs($owner)->get(route('user.edit', $t))->assertNotFound();
        $this->actingAs($owner)->patch(route('user.toggle-aktif', $t))->assertNotFound();
        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('Tim IT</');
        $this->assertNotContains('teknisi', \App\Support\Roles::values());
        // Owner tidak bisa membuat user ber-role teknisi
        $this->actingAs($owner)->post(route('user.store'), [
            'name' => 'Palsu', 'email' => 'palsu' . uniqid() . '@contoh.id', 'role' => 'teknisi',
            'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123',
        ])->assertSessionHasErrors('role');
        // Menu Tim IT tertutup untuk Owner
        $this->actingAs($owner)->get(route('tim-it.index'))->assertForbidden();
    }

    public function test_kelola_akun_tim_it(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => 'it2' . uniqid() . '@contoh.id',
            'password' => 'pendek1', 'password_confirmation' => 'pendek1'])->assertSessionHasErrors('password');
        $email = 'it2' . uniqid() . '@contoh.id';
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => $email,
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12'])->assertSessionHas('success');
        $baru = User::where('email', $email)->firstOrFail();
        $this->assertTrue($baru->isTeknisi());

        // Tidak bisa menonaktifkan diri sendiri
        $this->actingAs($t)->post(route('tim-it.toggle', $t))->assertSessionHas('error');
        $this->actingAs($t)->post(route('tim-it.toggle', $baru))->assertSessionHas('success');
        $this->assertFalse($baru->fresh()->is_active);
        // Bukan akun Tim IT → 404
        $this->actingAs($t)->post(route('tim-it.toggle', $this->buatUser('kasir')))->assertNotFound();
    }

    public function test_aktivitas_tim_it_tercatat_dan_hanya_tim_it_yang_melihat(): void
    {
        $t = $this->buatUser('teknisi');
        $mulai = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Tiga', 'email' => 'it3' . uniqid() . '@contoh.id',
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12']);
        $tindakan = DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'like', 'Tindakan:%')->latest('id')->first();
        $this->assertNotNull($tindakan);
        $this->assertStringNotContainsString('KataSandiPanjang12', (string) $tindakan->properties, 'isi formulir tidak disimpan');
        $this->assertGreaterThan($mulai, DB::table('activity_log')->where('log_name', 'TimIT')->count());

        // Login dicatat
        \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Auth\Events\Login('web', $t, false));
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'Login')->exists());

        // Khusus Tim IT (keputusan Owner 2026-11-16): Owner pun tidak bisa melihat
        $this->actingAs($t)->get(route('aktivitas-tim-it.index'))->assertOk()->assertSee($t->name);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_pusat'))->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertDontSee(route('aktivitas-tim-it.index'), false);

        // Audit Log pelanggan tidak memuat catatan Tim IT maupun perubahan oleh akun Tim IT
        $this->actingAs($owner)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertDontSee('Tindakan: tim-it.store');
        $this->actingAs($owner)->get(route('audit-log.index'))->assertOk()->assertDontSee('it3');
        $this->actingAs($owner)->get(route('audit-log.show', $tindakan->id))->assertNotFound();
        $this->actingAs($t)->get(route('audit-log.show', $tindakan->id))->assertOk();
        $this->actingAs($t)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertSee('Tindakan: tim-it.store');
        // Tindakan Owner tidak masuk catatan Tim IT
        $jumlah = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($this->buatUser('owner'))->post(route('pengaturan.umum.update'), ['nama_perusahaan' => "D'mentai"]);
        $this->assertSame($jumlah, DB::table('activity_log')->where('log_name', 'TimIT')->count());
    }
}
