<?php

namespace Tests\Feature\Tahap7;

use App\Support\Paket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Penjaga Paket: setiap link sidebar milik modul yang dimatikan harus hilang dari
 * menu Owner (supaya tidak ada menu yang terlihat tapi berujung "tidak termasuk paket").
 */
class PaketSidebarGuardTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function routeSidebar(): array
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $nav = substr($layout, strpos($layout, '<nav id="sidebar"'));
        preg_match_all("/route\\('([a-z0-9\\.\\-_]+)'\\)/", $nav, $m);

        return array_values(array_unique($m[1]));
    }

    /** Modul + semua modul yang membutuhkannya (langsung/tidak langsung). */
    private function denganPenerus(string $modul): array
    {
        $mati = [$modul];
        do {
            $tambah = false;
            foreach (Paket::MODUL as $k => $def) {
                if (! in_array($k, $mati, true) && array_intersect($def[2], $mati)) {
                    $mati[] = $k;
                    $tambah = true;
                }
            }
        } while ($tambah);

        return $mati;
    }

    public function test_link_sidebar_modul_mati_tidak_tampil_untuk_owner(): void
    {
        $owner = $this->buatUser('owner', \App\Models\Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'));
        $route = $this->routeSidebar();
        $bocor = [];

        foreach (array_keys(Paket::MODUL) as $modul) {
            $mati = $this->denganPenerus($modul);
            Paket::simpan(array_values(array_diff(array_keys(Paket::MODUL), $mati)), null, 7, null);
            $html = $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertOk()->getContent();
            $nav = substr($html, (int) strpos($html, '<nav id="sidebar"'));
            $nav = substr($nav, 0, (int) strpos($nav, '</nav>'));
            foreach ($route as $r) {
                if (! in_array(Paket::modulUntukRoute($r), $mati, true)) {
                    continue;
                }
                try {
                    $url = route($r);
                } catch (\Throwable) {
                    continue;
                }
                if (str_contains($nav, 'href="' . $url . '"')) {
                    $bocor[] = "{$modul}: {$r}";
                }
            }
        }
        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);

        $this->assertSame([], $bocor, 'Link sidebar masih tampil walau modulnya mati');
    }
}
