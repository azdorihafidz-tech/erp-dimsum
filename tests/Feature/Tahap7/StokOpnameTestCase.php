<?php

namespace Tests\Feature\Tahap7;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StokOpname;
use App\Models\StokRusak;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\StokOpnameService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Dasar tes Stok Opname (RANCANGAN_STOK_OPNAME.md): lokasi, user per peran,
 * barang + batch FIFO, dan langkah-langkah alur lewat HTTP.
 */
abstract class StokOpnameTestCase extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    protected Cabang $cabang;
    protected User $manajer;   // pemeriksa (ajukan)
    protected User $kasir;     // penghitung (hitung buta)
    protected User $admin;     // penyetuju

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->cabang = Cabang::where('tipe', 'cabang')->orderBy('id')->firstOrFail();
        DB::table('pengaturan_umums')->update(['opname_hitung_buta' => true, 'opname_batas_foto_hilang' => 200000]);
        DB::table('closings')->where('cabang_id', $this->cabang->id)->delete();
        $this->manajer = $this->buatUser('manajer_cabang', $this->cabang->id);
        $this->kasir = $this->buatUser('kasir', $this->cabang->id);
        $this->admin = $this->buatUser('admin_pusat');
    }

    protected function barang(string $nama, string $satuan = 'pcs', array $batch = [[10, 1000]]): Item
    {
        $item = Item::create([
            'cabang_id' => $this->cabang->id, 'kode_item' => 'SO' . strtoupper(substr(md5($nama . uniqid()), 0, 8)),
            'nama_item' => $nama, 'tipe' => 'bahan_baku', 'satuan' => $satuan, 'harga_beli_terakhir' => 500,
            'harga_jual' => 0, 'is_active' => true, 'track_stok' => true,
        ]);
        foreach ($batch as [$qty, $harga]) {
            app(StokService::class)->masuk($item->id, $this->cabang->id, $qty, 'stok awal uji', null, null, $harga);
        }

        return $item;
    }

    protected function stok(Item $i): float
    {
        return (float) Stock::where('item_id', $i->id)->where('lokasi_id', $this->cabang->id)->value('qty');
    }

    protected function mulai(array $items, ?User $oleh = null): StokOpname
    {
        $this->actingAs($oleh ?? $this->manajer);
        $res = $this->post(route('stok-opname.store'), [
            'cabang_id' => $this->cabang->id, 'cakupan' => 'barang',
            'cakupan_ids' => collect($items)->pluck('id')->all(), 'penghitung' => [$this->kasir->id],
        ]);
        $res->assertRedirect();

        return StokOpname::latest('id')->firstOrFail();
    }

    protected function hitung(StokOpname $o, Item $i, $qty, ?User $oleh = null)
    {
        $this->actingAs($oleh ?? $this->kasir);

        return $this->postJson(route('stok-opname.hitung', $o), ['item_id' => $i->id, 'qty' => $qty]);
    }

    protected function rincian(StokOpname $o, Item $i, array $rows)
    {
        $this->actingAs($this->manajer);

        return $this->post(route('stok-opname.rincian', $o), ['item_id' => $i->id, 'rincian' => $rows]);
    }

    protected function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('rusak.jpg');
    }

    protected function hitungOleh(StokOpname $o, User $u): void
    {
        $o->items()->update(['dihitung_by' => $u->id]);
    }
}
