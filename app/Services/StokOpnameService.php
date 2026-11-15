<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\PenyebabSelisihStok;
use App\Enums\RoleUser;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\PengaturanUmum;
use App\Models\Stock;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\StokOpnameRincian;
use App\Models\StokRusak;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Notifications\StokOpnameNotification;
use App\Support\ClosingLock;
use App\Support\KonversiSatuan;
use App\Support\StokOpnameAkun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Semua aturan Stok Opname (RANCANGAN_STOK_OPNAME.md): mulai, hitung, hitung
 * ulang, keluarkan barang, rincian penyebab, ajukan, tolak, setujui, batal, hak.
 * Controller hanya meneruskan. Pesan error = RuntimeException (bahasa awam).
 *
 * Prinsip: stok & keuangan baru berubah saat DISETUJUI, dan yang diterapkan
 * adalah SELISIH (fisik − stok sistem saat barang itu dihitung), bukan menimpa
 * angka stok — penjualan sesudah barang dihitung tidak terhapus.
 */
class StokOpnameService
{
    public const REF = 'stok_opname';

    public function __construct(private StokService $stok) {}

    // ===================== Hak =====================

    public static function isOwner(?User $user): bool
    {
        return $user && $user->role === RoleUser::Owner;
    }

    public static function bolehAksesLokasi(User $user, int $cabangId): bool
    {
        return $user->canAccessAllBranches() || $user->cabangs()->where('cabangs.id', $cabangId)->exists();
    }

    public function bolehLihat(User $user, StokOpname $o): bool
    {
        $izin = $user->hasPermission('stok_opname.view') || $user->hasPermission('stok_opname.hitung')
            || $user->hasPermission('stok_opname.ajukan') || $user->hasPermission('stok_opname.setujui');

        return $izin && self::bolehAksesLokasi($user, $o->cabang_id);
    }

    /** Pemeriksa = izin Ajukan (mulai, lihat selisih, rincian, ajukan) atau Setujui. */
    public function bolehPeriksa(User $user, StokOpname $o): bool
    {
        return ($user->hasPermission('stok_opname.ajukan') || $user->hasPermission('stok_opname.setujui'))
            && self::bolehAksesLokasi($user, $o->cabang_id);
    }

    public function bolehHitung(User $user, StokOpname $o): bool
    {
        if (! $user->hasPermission('stok_opname.hitung') || ! self::bolehAksesLokasi($user, $o->cabang_id)) {
            return false;
        }

        return self::isOwner($user) || $user->hasPermission('stok_opname.ajukan')
            || $o->penghitung()->where('users.id', $user->id)->exists();
    }

    /** Stok sistem & selisih hanya terlihat oleh pemeriksa/penyetuju, atau kalau hitung buta dimatikan. */
    public function bolehLihatSistem(User $user, StokOpname $o): bool
    {
        return ! $o->hitung_buta || $this->bolehPeriksa($user, $o);
    }

    /** @param bool $cekPenghitung false = hanya cek izin & lokasi */
    public function bolehSetujui(User $user, StokOpname $o, bool $cekPenghitung = true): bool
    {
        if (! $user->hasPermission('stok_opname.setujui') || ! self::bolehAksesLokasi($user, $o->cabang_id)) {
            return false;
        }

        return ! $cekPenghitung || self::isOwner($user) || ! $this->ikutMenghitung($user, $o);
    }

    public function ikutMenghitung(User $user, StokOpname $o): bool
    {
        return $o->items()->where('dihitung_by', $user->id)->exists();
    }

    private function pastikan(bool $boleh, string $pesan = 'Anda tidak punya izin untuk tindakan ini.'): void
    {
        if (! $boleh) {
            abort(403, $pesan);
        }
    }

    private function pastikanStatus(StokOpname $o, string $status, string $pesan): void
    {
        if ($o->status !== $status) {
            throw new RuntimeException($pesan);
        }
    }

    // ===================== Bantuan =====================

    /** Satuan berat/volume boleh desimal; satuan lain (pcs, porsi, pack, …) bulat. */
    public static function bolehDesimal(?string $satuan): bool
    {
        $s = strtolower(trim((string) $satuan));
        foreach (KonversiSatuan::FAKTOR as $faktor) {
            if (isset($faktor[$s])) {
                return true;
            }
        }

        return in_array($s, ['kilo', 'kilogram', 'gram', 'liter', 'litre', 'mililiter'], true);
    }

    public static function angka(?float $n): string
    {
        return $n === null ? '—' : rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    public static function setting(): PengaturanUmum
    {
        return PengaturanUmum::query()->first() ?? PengaturanUmum::getSetting();
    }

    /** Barang yang bisa diopname di lokasi ini (milik lokasi, punya stok dilacak). */
    public static function barangLokasi(int $cabangId)
    {
        return Item::query()
            ->whereIn('items.tipe', Item::TIPE_DAPAT_DIBELI)
            ->where('items.track_stok', true)
            ->where(fn ($q) => $q->where('items.cabang_id', $cabangId)
                ->orWhere(fn ($w) => $w->whereNull('items.cabang_id')->whereExists(fn ($s) => $s->from('stocks')
                    ->whereColumn('stocks.item_id', 'items.id')->where('stocks.lokasi_id', $cabangId)->whereNull('stocks.deleted_at'))));
    }

    /** Barang yang sedang dihitung di opname berjalan lain di lokasi ini: item_id => nomor. */
    public static function barangTerkunci(int $cabangId, ?int $kecualiOpnameId = null): Collection
    {
        return DB::table('stok_opname_items as i')
            ->join('stok_opnames as o', 'o.id', '=', 'i.stok_opname_id')
            ->where('o.cabang_id', $cabangId)->whereIn('o.status', StokOpname::BERJALAN)
            ->where('i.dikeluarkan', false)
            ->when($kecualiOpnameId, fn ($q) => $q->where('o.id', '!=', $kecualiOpnameId))
            ->pluck('o.nomor', 'i.item_id');
    }

    private function nomorBaru(): string
    {
        $prefix = 'SO-' . now()->format('Ym') . '-';
        $last = StokOpname::where('nomor', 'like', $prefix . '%')->lockForUpdate()->max('nomor');
        $urut = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    // ===================== Mulai =====================

    /**
     * @param array{cabang_id:int, cakupan:string, cakupan_ids?:array, penghitung?:array, catatan?:?string} $data
     */
    public function mulai(array $data, User $user): StokOpname
    {
        $cabang = Cabang::where('is_active', true)->find((int) $data['cabang_id']);
        if (! $cabang) {
            throw new RuntimeException('Lokasi tidak ditemukan atau tidak aktif.');
        }
        $this->pastikan($user->hasPermission('stok_opname.ajukan') && self::bolehAksesLokasi($user, $cabang->id),
            'Anda tidak punya izin memulai stok opname di lokasi ini.');

        $cakupan = in_array($data['cakupan'] ?? 'semua', ['semua', 'kategori', 'barang'], true) ? $data['cakupan'] : 'semua';
        $ids = array_values(array_unique(array_map('intval', $data['cakupan_ids'] ?? [])));
        if ($cakupan !== 'semua' && ! $ids) {
            throw new RuntimeException($cakupan === 'kategori' ? 'Pilih minimal satu kategori.' : 'Pilih minimal satu barang.');
        }

        $barang = self::barangLokasi($cabang->id)
            ->when($cakupan === 'kategori', fn ($q) => $q->whereIn('items.item_category_id', $ids))
            ->when($cakupan === 'barang', fn ($q) => $q->whereIn('items.id', $ids))
            ->orderBy('items.nama_item')->get(['items.id', 'items.nama_item']);
        if ($barang->isEmpty()) {
            throw new RuntimeException('Tidak ada barang yang bisa dihitung untuk pilihan ini di ' . $cabang->nama_cabang . '.');
        }

        $terkunci = self::barangTerkunci($cabang->id);
        $bentrok = $barang->filter(fn ($b) => $terkunci->has($b->id));
        if ($bentrok->isNotEmpty()) {
            $contoh = $bentrok->take(3)->map(fn ($b) => $b->nama_item . ' (' . $terkunci[$b->id] . ')')->implode(', ');
            throw new RuntimeException("{$bentrok->count()} barang sudah ada di stok opname lain yang belum selesai di lokasi ini, mis. {$contoh}. Selesaikan/batalkan opname itu, atau pilih barang lain.");
        }

        $penghitung = collect($data['penghitung'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $penghitungValid = User::whereIn('id', $penghitung)->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission('stok_opname.hitung') && self::bolehAksesLokasi($u, $cabang->id));
        if ($penghitungValid->count() !== $penghitung->count()) {
            throw new RuntimeException('Ada penghitung yang tidak punya izin Hitung Stok Opname atau tidak punya akses ke lokasi ini.');
        }
        if ($penghitungValid->isEmpty()) {
            $penghitungValid = collect([$user]);
        }

        return DB::transaction(function () use ($cabang, $cakupan, $ids, $barang, $penghitungValid, $data, $user) {
            $opname = StokOpname::create([
                'nomor'       => $this->nomorBaru(),
                'cabang_id'   => $cabang->id,
                'cakupan'     => $cakupan,
                'cakupan_ids' => $cakupan === 'semua' ? null : $ids,
                'hitung_buta' => (bool) (self::setting()->opname_hitung_buta ?? true),
                'status'      => StokOpname::MENGHITUNG,
                'catatan'     => $data['catatan'] ?? null,
                'dibuat_by'   => $user->id,
            ]);
            $now = now();
            StokOpnameItem::insert($barang->map(fn ($b) => [
                'stok_opname_id' => $opname->id, 'item_id' => $b->id, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
            $opname->penghitung()->sync($penghitungValid->pluck('id')->all());

            activity('StokOpname')->performedOn($opname)->causedBy($user)
                ->log("Memulai stok opname {$opname->nomor} di {$cabang->nama_cabang} ({$barang->count()} barang)");

            return $opname;
        });
    }

    // ===================== Hitung =====================

    private function baris(StokOpname $o, int $itemId): StokOpnameItem
    {
        $row = $o->items()->where('item_id', $itemId)->first();
        if (! $row) {
            throw new RuntimeException('Barang ini tidak ada di stok opname ini.');
        }

        return $row;
    }

    /** Simpan jumlah fisik satu barang (simpan otomatis dari layar hitung). */
    public function hitung(StokOpname $o, int $itemId, $qty, User $user): StokOpnameItem
    {
        $this->pastikan($this->bolehHitung($user, $o), 'Anda bukan penghitung opname ini.');
        $this->pastikanStatus($o, StokOpname::MENGHITUNG, 'Opname ini tidak sedang di tahap hitung.');
        $row = $this->baris($o, $itemId);
        if ($row->dikeluarkan) {
            throw new RuntimeException('Barang ini sudah dikeluarkan dari opname.');
        }

        if ($qty === null || $qty === '') {
            // Dikosongkan = batal hitung barang ini
            $row->update(['qty_fisik' => null, 'qty_sistem' => null, 'dihitung_at' => null, 'dihitung_by' => null]);
            $row->rincian()->get()->each(fn ($r) => $this->hapusRincian($r));

            return $row->fresh();
        }

        $nilai = str_replace(',', '.', (string) $qty);
        if (! is_numeric($nilai) || (float) $nilai < 0) {
            throw new RuntimeException('Jumlah fisik harus angka 0 atau lebih.');
        }
        $nilai = round((float) $nilai, 3);
        $item = $row->item;
        if (! self::bolehDesimal($item?->satuan) && abs($nilai - round($nilai)) > 0.0001) {
            throw new RuntimeException("Satuan {$item?->satuan} harus bilangan bulat (tanpa koma).");
        }

        $sistem = $this->stok->getStok($itemId, $o->cabang_id);
        $selisihLama = $row->selisih();
        $row->update([
            'qty_fisik'    => $nilai,
            'qty_sistem'   => $sistem,
            'dihitung_at'  => now(),
            'dihitung_by'  => $user->id,
            'hitung_ulang' => false,
        ]);
        // Selisih berubah → rincian lama tidak berlaku lagi
        if ($selisihLama === null || abs($selisihLama - (float) $row->selisih()) > 0.0005) {
            $row->rincian()->get()->each(fn ($r) => $this->hapusRincian($r));
        }

        return $row->fresh();
    }

    /** "Scan": kode item → jumlah fisik +1. */
    public function scan(StokOpname $o, string $kode, User $user): StokOpnameItem
    {
        $kode = trim($kode);
        $row = $o->items()->whereHas('item', fn ($q) => $q->whereRaw('LOWER(kode_item) = ?', [strtolower($kode)]))->first();
        if (! $row) {
            throw new RuntimeException("Kode \"{$kode}\" tidak ditemukan di daftar opname ini.");
        }
        $awal = $row->sudahDihitung() ? (float) $row->qty_fisik : 0;

        return $this->hitung($o, $row->item_id, $awal + 1, $user);
    }

    public function mintaHitungUlang(StokOpname $o, int $itemId, User $user): StokOpnameItem
    {
        $this->pastikan($this->bolehPeriksa($user, $o));
        $this->pastikanStatus($o, StokOpname::MENGHITUNG, 'Hitung ulang hanya bisa sebelum diajukan.');
        $row = $this->baris($o, $itemId);
        $row->update(['hitung_ulang' => true]);

        return $row->fresh();
    }

    public function keluarkan(StokOpname $o, int $itemId, bool $keluar, ?string $alasan, User $user): StokOpnameItem
    {
        $this->pastikan($this->bolehPeriksa($user, $o));
        $this->pastikanStatus($o, StokOpname::MENGHITUNG, 'Barang hanya bisa dikeluarkan sebelum diajukan.');
        $row = $this->baris($o, $itemId);
        if ($keluar) {
            if (trim((string) $alasan) === '') {
                throw new RuntimeException('Tulis alasan barang ini tidak dihitung.');
            }
            $row->rincian()->get()->each(fn ($r) => $this->hapusRincian($r));
            $row->update(['dikeluarkan' => true, 'alasan_dikeluarkan' => mb_substr(trim($alasan), 0, 255)]);
        } else {
            $terkunci = self::barangTerkunci($o->cabang_id, $o->id);
            if ($terkunci->has($itemId)) {
                throw new RuntimeException('Barang ini sekarang ada di stok opname ' . $terkunci[$itemId] . '.');
            }
            $row->update(['dikeluarkan' => false, 'alasan_dikeluarkan' => null]);
        }

        return $row->fresh();
    }

    // ===================== Rincian penyebab =====================

    private function hapusRincian(StokOpnameRincian $r): void
    {
        if ($r->foto_path) {
            Storage::disk('public')->delete($r->foto_path);
        }
        $r->delete();
    }

    /** Batas nilai Hilang yang wajib foto (0 = semua wajib). */
    public static function batasFotoHilang(): float
    {
        return (float) (self::setting()->opname_batas_foto_hilang ?? 200000);
    }

    /**
     * Ganti semua rincian penyebab satu barang yang fisiknya KURANG.
     *
     * @param array<int, array{penyebab:string, qty:mixed, catatan?:?string, foto?:?UploadedFile, foto_lama?:?string}> $rows
     */
    public function simpanRincian(StokOpname $o, int $itemId, array $rows, User $user): StokOpnameItem
    {
        $this->pastikan($this->bolehPeriksa($user, $o));
        $this->pastikanStatus($o, StokOpname::MENGHITUNG, 'Rincian hanya bisa diubah sebelum diajukan.');
        $row = $this->baris($o, $itemId);
        if (! $row->sudahDihitung() || $row->dikeluarkan) {
            throw new RuntimeException('Barang ini belum dihitung.');
        }
        $selisih = (float) $row->selisih();
        if ($selisih >= -0.0005) {
            throw new RuntimeException('Rincian penyebab hanya untuk barang yang fisiknya kurang.');
        }

        $lama = $row->rincian()->get()->keyBy('id');
        $bersih = [];
        foreach ($rows as $i => $r) {
            $qty = round((float) str_replace(',', '.', (string) ($r['qty'] ?? 0)), 3);
            if ($qty <= 0 && empty($r['penyebab'])) {
                continue;
            }
            $penyebab = PenyebabSelisihStok::tryFrom((string) ($r['penyebab'] ?? ''));
            if (! $penyebab || $penyebab === PenyebabSelisihStok::Lebih) {
                throw new RuntimeException('Pilih penyebab di baris ' . ($i + 1) . '.');
            }
            if ($qty <= 0) {
                throw new RuntimeException('Jumlah di baris ' . ($i + 1) . ' harus lebih dari 0.');
            }
            $fotoLama = isset($r['rincian_id'], $lama[(int) $r['rincian_id']]) ? $lama[(int) $r['rincian_id']]->foto_path : null;
            $bersih[] = ['penyebab' => $penyebab, 'qty' => $qty, 'catatan' => trim((string) ($r['catatan'] ?? '')) ?: null,
                'foto' => $r['foto'] ?? null, 'foto_lama' => $fotoLama];
        }

        $total = round(array_sum(array_column($bersih, 'qty')), 3);
        if (abs($total - abs($selisih)) > 0.0005) {
            throw new RuntimeException('Jumlah rincian (' . self::angka($total) . ') harus sama dengan selisih (' . self::angka(abs($selisih)) . ').');
        }
        $this->cekFoto($o, $row, array_map(fn ($b) => [$b['penyebab'], $b['qty'], $b['foto'] || $b['foto_lama']], $bersih));

        return DB::transaction(function () use ($o, $row, $bersih, $lama) {
            $dipakai = [];
            foreach ($bersih as $b) {
                $path = $b['foto'] instanceof UploadedFile
                    ? $b['foto']->store('stok-opname/' . $o->id, 'public')
                    : $b['foto_lama'];
                if ($b['foto_lama'] && $path === $b['foto_lama']) {
                    $dipakai[] = $path;
                }
                $row->rincian()->create([
                    'penyebab' => $b['penyebab']->value, 'qty' => $b['qty'], 'catatan' => $b['catatan'], 'foto_path' => $path,
                ]);
            }
            foreach ($lama as $r) {
                if ($r->foto_path && ! in_array($r->foto_path, $dipakai, true)) {
                    Storage::disk('public')->delete($r->foto_path);
                }
                $r->delete();
            }

            return $row->fresh('rincian');
        });
    }

    /** @param array<int, array{0: PenyebabSelisihStok, 1: float, 2: bool}> $baris [penyebab, qty, adaFoto] */
    private function cekFoto(StokOpname $o, StokOpnameItem $row, array $baris): void
    {
        $nama = $row->item?->nama_item;
        $qtyHilang = 0.0;
        foreach ($baris as [$p, $qty, $ada]) {
            if ($p->selaluWajibFoto() && ! $ada) {
                throw new RuntimeException("Foto wajib untuk penyebab {$p->labelPendek()} ({$nama}).");
            }
            if ($p === PenyebabSelisihStok::Hilang) {
                $qtyHilang += $qty;
            }
        }
        if ($qtyHilang > 0) {
            $nilai = $this->stok->perkiraanNilaiKeluar($row->item_id, $o->cabang_id, $qtyHilang);
            $batas = self::batasFotoHilang();
            if ($nilai >= $batas) {
                foreach ($baris as [$p, , $ada]) {
                    if ($p === PenyebabSelisihStok::Hilang && ! $ada) {
                        throw new RuntimeException("Foto wajib untuk barang hilang senilai Rp " . number_format($nilai, 0, ',', '.')
                            . ($batas > 0 ? ' (batas Rp ' . number_format($batas, 0, ',', '.') . ')' : '') . " — {$nama}.");
                    }
                }
            }
        }
    }

    // ===================== Ajukan / Tolak / Batal =====================

    /** Daftar masalah yang menghalangi pengajuan (kosong = boleh diajukan). */
    public function masalahPengajuan(StokOpname $o): array
    {
        $masalah = [];
        $rows = $o->items()->with(['item', 'rincian'])->where('dikeluarkan', false)->get();
        if ($rows->isEmpty()) {
            return ['Tidak ada barang yang dihitung.'];
        }
        $belum = $rows->filter(fn ($r) => ! $r->sudahDihitung());
        if ($belum->isNotEmpty()) {
            $masalah[] = $belum->count() . ' barang belum dihitung / diminta hitung ulang (mis. ' . $belum->take(3)->map(fn ($r) => $r->item?->nama_item)->implode(', ') . ').';
        }
        foreach ($rows->filter(fn ($r) => $r->sudahDihitung() && $r->selisih() < -0.0005) as $r) {
            $total = round((float) $r->rincian->sum('qty'), 3);
            if (abs($total - abs((float) $r->selisih())) > 0.0005) {
                $masalah[] = "Rincian penyebab {$r->item?->nama_item} belum lengkap (" . self::angka($total) . ' dari ' . self::angka(abs((float) $r->selisih())) . ').';
                continue;
            }
            try {
                $this->cekFoto($o, $r, $r->rincian->map(fn ($x) => [$x->penyebab, (float) $x->qty, (bool) $x->foto_path])->all());
            } catch (RuntimeException $e) {
                $masalah[] = $e->getMessage();
            }
        }

        return $masalah;
    }

    public function ajukan(StokOpname $o, User $user): StokOpname
    {
        $this->pastikan($this->bolehPeriksa($user, $o) && $user->hasPermission('stok_opname.ajukan'));
        $this->pastikanStatus($o, StokOpname::MENGHITUNG, 'Opname ini tidak sedang di tahap hitung.');
        $masalah = $this->masalahPengajuan($o);
        if ($masalah) {
            throw new RuntimeException('Belum bisa diajukan: ' . implode(' ', $masalah));
        }

        $o->update(['status' => StokOpname::DIAJUKAN, 'diajukan_at' => now(), 'diajukan_by' => $user->id]);
        activity('StokOpname')->performedOn($o)->causedBy($user)->log("Mengajukan stok opname {$o->nomor}");
        $this->kabari($this->penyetujuUntuk($o), $o, 'Stok opname menunggu persetujuan',
            "{$o->nomor} di {$o->cabang?->nama_cabang} diajukan oleh {$user->name}.", 'warning');

        return $o->fresh();
    }

    public function tolak(StokOpname $o, string $alasan, User $user): StokOpname
    {
        $this->pastikan($this->bolehSetujui($user, $o, false));
        $this->pastikanStatus($o, StokOpname::DIAJUKAN, 'Hanya opname yang menunggu persetujuan yang bisa ditolak.');
        if (trim($alasan) === '') {
            throw new RuntimeException('Alasan penolakan wajib diisi.');
        }
        $o->update(['status' => StokOpname::MENGHITUNG, 'ditolak_at' => now(), 'ditolak_by' => $user->id,
            'alasan_tolak' => trim($alasan), 'diajukan_at' => null, 'diajukan_by' => null]);
        activity('StokOpname')->performedOn($o)->causedBy($user)->log("Menolak stok opname {$o->nomor}: {$alasan}");
        $this->kabari(User::whereIn('id', array_filter([$o->dibuat_by]))->get(), $o, 'Stok opname ditolak',
            "{$o->nomor}: {$alasan}", 'danger');

        return $o->fresh();
    }

    public function batal(StokOpname $o, string $alasan, User $user): StokOpname
    {
        $this->pastikan($user->hasPermission('stok_opname.batal') && self::bolehAksesLokasi($user, $o->cabang_id));
        if (! $o->berjalan()) {
            throw new RuntimeException('Opname yang sudah disetujui atau dibatalkan tidak bisa dibatalkan.');
        }
        if (trim($alasan) === '') {
            throw new RuntimeException('Alasan pembatalan wajib diisi.');
        }
        $o->update(['status' => StokOpname::DIBATALKAN, 'dibatalkan_at' => now(), 'dibatalkan_by' => $user->id, 'alasan_batal' => trim($alasan)]);
        activity('StokOpname')->performedOn($o)->causedBy($user)->log("Membatalkan stok opname {$o->nomor}: {$alasan}");

        return $o->fresh();
    }

    // ===================== Setujui =====================

    public function setujui(StokOpname $o, User $user): StokOpname
    {
        $this->pastikan($this->bolehSetujui($user, $o, false));
        if (! $this->bolehSetujui($user, $o)) {
            throw new RuntimeException('Anda ikut menghitung opname ini, jadi tidak bisa menyetujuinya sendiri. Minta penyetuju lain.');
        }
        $this->pastikanStatus($o, StokOpname::DIAJUKAN, 'Hanya opname yang menunggu persetujuan yang bisa disetujui.');
        if (ClosingLock::terkunci($o->cabang_id, today())) {
            throw new RuntimeException('Hari ini sudah di-Closing / Tutup Buku untuk lokasi ini, jadi kerugian stok tidak bisa dicatat. Buka kunci Closing hari ini, atau setujui besok.');
        }
        $masalah = $this->masalahPengajuan($o);
        if ($masalah) {
            throw new RuntimeException('Belum bisa disetujui: ' . implode(' ', $masalah));
        }

        StokOpnameAkun::pastikan();

        $hasil = DB::transaction(function () use ($o, $user) {
            $o = StokOpname::lockForUpdate()->findOrFail($o->id);
            $this->pastikanStatus($o, StokOpname::DIAJUKAN, 'Opname ini sudah diproses orang lain.');
            $cabangId = $o->cabang_id;
            $rows = $o->items()->with(['item', 'rincian'])->where('dikeluarkan', false)->get();

            // Kunci baris stok, lalu cek dulu semua kekurangan masih bisa dipotong
            $stokSekarang = [];
            foreach ($rows as $r) {
                Stock::withTrashed()->where('item_id', $r->item_id)->where('lokasi_id', $cabangId)->lockForUpdate()->first();
                $stokSekarang[$r->item_id] = $this->stok->getStok($r->item_id, $cabangId);
            }
            $kurangStok = [];
            foreach ($rows as $r) {
                $s = (float) $r->selisih();
                if ($s < -0.0005 && $stokSekarang[$r->item_id] + 0.0005 < abs($s)) {
                    $kurangStok[] = "{$r->item?->nama_item}: stok sekarang " . self::angka($stokSekarang[$r->item_id])
                        . ", perlu dikurangi " . self::angka(abs($s));
                }
            }
            if ($kurangStok) {
                throw new RuntimeException('Stok sekarang sudah tidak cukup untuk dikurangi (keburu terjual/terpakai sesudah dihitung): '
                    . implode('; ', $kurangStok) . '. Tolak opname ini dan hitung ulang barang tersebut.');
            }

            $perKategori = []; // kode kategori => [nilai, tipe, rincian ids, barang]
            $ringkas = ['dihitung' => 0, 'cocok' => 0, 'kurang' => 0, 'lebih' => 0, 'nilai_kurang' => 0.0, 'nilai_lebih' => 0.0, 'nilai_sistem' => 0.0];

            foreach ($rows as $r) {
                $ringkas['dihitung']++;
                $selisih = (float) $r->selisih();
                $nama = (string) $r->item?->nama_item;
                $satuan = (string) $r->item?->satuan;
                $hargaRata = $this->stok->hargaPokokRataRata($r->item_id, $cabangId);
                $ringkas['nilai_sistem'] += max(0, (float) $r->qty_sistem) * $hargaRata;

                if (abs($selisih) <= 0.0005) {
                    $ringkas['cocok']++;
                    $r->update(['harga_pokok' => $hargaRata, 'nilai_selisih' => 0]);
                    continue;
                }

                if ($selisih > 0) {
                    $ringkas['lebih']++;
                    $nilai = round($selisih * $hargaRata, 2);
                    $mv = $this->stok->tambahPenyesuaian($r->item_id, $cabangId, $selisih, $hargaRata,
                        "Stok Opname {$o->nomor}: lebih " . self::angka($selisih) . " {$satuan}", self::REF, $o->id);
                    $rin = $r->rincian()->create(['penyebab' => PenyebabSelisihStok::Lebih->value, 'qty' => $selisih,
                        'nilai' => $nilai, 'stock_movement_id' => $mv]);
                    $perKategori = $this->tambahKategori($perKategori, 'OPN-LEBIH', 'pemasukan', $nilai, $rin->id, $nama);
                    $ringkas['nilai_lebih'] += $nilai;
                    $r->update(['harga_pokok' => $hargaRata, 'nilai_selisih' => $nilai]);
                    continue;
                }

                $ringkas['kurang']++;
                $totalNilai = 0.0;
                foreach ($r->rincian as $rin) {
                    $hasil = $this->stok->kurangiPenyesuaian($r->item_id, $cabangId, (float) $rin->qty,
                        "Stok Opname {$o->nomor}: {$rin->penyebab->labelPendek()} " . self::angka((float) $rin->qty) . " {$satuan}", self::REF, $o->id);
                    $rin->update(['nilai' => $hasil['nilai'], 'stock_movement_id' => $hasil['movement_id']]);
                    $totalNilai += $hasil['nilai'];
                    $perKategori = $this->tambahKategori($perKategori, $rin->penyebab->kodeKategori(), 'pengeluaran', $hasil['nilai'], $rin->id, $nama);

                    if ($rin->penyebab->keStokRusak()) {
                        StokRusak::create([
                            'cabang_id' => $cabangId, 'item_id' => $r->item_id, 'stok_opname_rincian_id' => $rin->id,
                            'penyebab' => $rin->penyebab->value, 'qty_awal' => $rin->qty, 'qty_sisa' => $rin->qty,
                            'harga_pokok' => (float) $rin->qty > 0 ? round($hasil['nilai'] / (float) $rin->qty, 4) : 0,
                            'tanggal' => today(), 'status' => 'tertahan',
                        ]);
                    }
                }
                $ringkas['nilai_kurang'] += $totalNilai;
                $r->update(['harga_pokok' => abs($selisih) > 0 ? round($totalNilai / abs($selisih), 4) : $hargaRata, 'nilai_selisih' => -round($totalNilai, 2)]);
            }

            // Satu baris keuangan per penyebab (tanpa kas)
            foreach ($perKategori as $kode => $k) {
                if (round($k['nilai'], 2) <= 0) {
                    continue;
                }
                $label = StokOpnameAkun::KATEGORI[$kode][0];
                $barang = count($k['barang']);
                $tk = TransaksiKeuangan::create([
                    'cabang_id'         => $cabangId,
                    'kas_id'            => null,
                    'nomor_transaksi'   => $o->nomor . '-' . substr($kode, 4),
                    'tanggal_transaksi' => today()->toDateString(),
                    'tipe'              => $k['tipe'] === 'pemasukan' ? TipeTransaksiKeuangan::Pemasukan : TipeTransaksiKeuangan::Pengeluaran,
                    'kategori'          => KategoriEnum::Lainnya,
                    'kategori_id'       => StokOpnameAkun::kategoriId($kode),
                    'keterangan'        => "Stok Opname {$o->nomor}: {$label} — {$barang} barang (" . implode(', ', array_slice($k['barang'], 0, 3)) . ($barang > 3 ? ', …' : '') . ')',
                    'jumlah'            => round($k['nilai'], 2),
                    'referensi_type'    => self::REF,
                    'referensi_id'      => $o->id,
                    'created_by'        => $user->id,
                ]);
                StokOpnameRincian::whereIn('id', $k['rincian'])->update(['transaksi_keuangan_id' => $tk->id]);
            }

            $ringkas['nilai_kurang'] = round($ringkas['nilai_kurang'], 2);
            $ringkas['nilai_lebih'] = round($ringkas['nilai_lebih'], 2);
            $ringkas['nilai_bersih'] = round($ringkas['nilai_lebih'] - $ringkas['nilai_kurang'], 2);
            $ringkas['nilai_sistem'] = round($ringkas['nilai_sistem'], 2);
            $ringkas['akurasi_nilai'] = $ringkas['nilai_sistem'] > 0
                ? round(max(0, 1 - ($ringkas['nilai_kurang'] + $ringkas['nilai_lebih']) / $ringkas['nilai_sistem']) * 100, 2) : null;
            $akurasi = $ringkas['dihitung'] > 0 ? round($ringkas['cocok'] / $ringkas['dihitung'] * 100, 2) : null;

            $o->update(['status' => StokOpname::DISETUJUI, 'disetujui_at' => now(), 'disetujui_by' => $user->id,
                'tanggal_disetujui' => today(), 'ringkasan' => $ringkas, 'akurasi' => $akurasi]);
            activity('StokOpname')->performedOn($o)->causedBy($user)->withProperties($ringkas)
                ->log("Menyetujui stok opname {$o->nomor}");

            return $o;
        });

        $this->kabari(User::whereIn('id', array_filter([$hasil->dibuat_by, $hasil->diajukan_by]))->get(), $hasil,
            'Stok opname disetujui', "{$hasil->nomor} disetujui oleh {$user->name}. Stok & keuangan sudah disesuaikan.", 'success');

        return $hasil->fresh();
    }

    private function tambahKategori(array $per, string $kode, string $tipe, float $nilai, int $rincianId, string $nama): array
    {
        $per[$kode] ??= ['nilai' => 0.0, 'tipe' => $tipe, 'rincian' => [], 'barang' => []];
        $per[$kode]['nilai'] += $nilai;
        $per[$kode]['rincian'][] = $rincianId;
        if (! in_array($nama, $per[$kode]['barang'], true)) {
            $per[$kode]['barang'][] = $nama;
        }

        return $per;
    }

    // ===================== Berita Acara =====================

    /** Boleh mengunduh berita acara? Penghitung buta tidak boleh selama opname berjalan. */
    public function bolehUnduhBeritaAcara(User $user, StokOpname $o): bool
    {
        return $this->bolehLihat($user, $o) && (! $o->berjalan() || $this->bolehLihatSistem($user, $o));
    }

    /**
     * Data Berita Acara Stok Opname (PDF & Excel). Sebelum disetujui nilai =
     * perkiraan (FIFO sekarang) dan dokumen bertanda SEMENTARA.
     */
    public function dataBeritaAcara(StokOpname $o): array
    {
        $o->loadMissing(['cabang', 'pembuat', 'pengaju', 'penyetuju', 'penghitung']);
        $final = $o->status === StokOpname::DISETUJUI;
        $rows = $o->items()->with(['item.category', 'rincian', 'penghitung'])->get()
            ->sortBy(fn ($r) => mb_strtolower((string) $r->item?->nama_item))->values();

        $baris = [];
        $keluar = [];
        $foto = [];
        $rk = ['dihitung' => 0, 'cocok' => 0, 'kurang' => 0, 'lebih' => 0, 'nilai_kurang' => 0.0, 'nilai_lebih' => 0.0, 'nilai_sistem' => 0.0];
        foreach ($rows as $r) {
            if ($r->dikeluarkan) {
                $keluar[] = ['kode' => $r->item?->kode_item, 'nama' => $r->item?->nama_item, 'alasan' => $r->alasan_dikeluarkan];
                continue;
            }
            $s = $r->selisih();
            if ($final) {
                $harga = (float) $r->harga_pokok;
                $nilai = (float) $r->nilai_selisih;
            } else {
                $harga = $this->stok->hargaPokokRataRata($r->item_id, $o->cabang_id);
                $nilai = $s === null ? 0.0 : ($s < 0 ? -$this->stok->perkiraanNilaiKeluar($r->item_id, $o->cabang_id, abs($s)) : round($s * $harga, 2));
            }
            if ($s !== null) {
                $rk['dihitung']++;
                $rk['nilai_sistem'] += max(0, (float) $r->qty_sistem) * ($final ? $this->hargaUntukAkurasi($r, $harga) : $harga);
                if (abs($s) <= 0.0005) {
                    $rk['cocok']++;
                } elseif ($s < 0) {
                    $rk['kurang']++;
                    $rk['nilai_kurang'] += abs($nilai);
                } else {
                    $rk['lebih']++;
                    $rk['nilai_lebih'] += $nilai;
                }
            }
            $penyebab = $r->rincian->map(fn ($x) => $x->penyebab->labelPendek() . ' ' . self::angka((float) $x->qty))->implode(', ');
            foreach ($r->rincian as $x) {
                if ($x->foto_path) {
                    $foto[] = ['nama' => $r->item?->nama_item, 'penyebab' => $x->penyebab->labelPendek(), 'qty' => (float) $x->qty, 'path' => $x->foto_path, 'catatan' => $x->catatan];
                }
            }
            $baris[] = [
                'kode' => $r->item?->kode_item, 'nama' => $r->item?->nama_item, 'kategori' => $r->item?->category?->nama_kategori,
                'satuan' => $r->item?->satuan, 'sistem' => $r->qty_sistem === null ? null : (float) $r->qty_sistem,
                'fisik' => $r->qty_fisik === null ? null : (float) $r->qty_fisik, 'selisih' => $s, 'harga' => round($harga, 2),
                'nilai' => round($nilai, 2), 'penyebab' => $penyebab ?: ($s !== null && $s > 0.0005 ? 'Lebih' : ''),
                'penghitung' => $r->penghitung?->name,
            ];
        }
        if ($final && $o->ringkasan) {
            $rk = array_merge($rk, $o->ringkasan);
        }
        $rk['nilai_kurang'] = round($rk['nilai_kurang'], 2);
        $rk['nilai_lebih'] = round($rk['nilai_lebih'], 2);
        $rk['nilai_bersih'] = round($rk['nilai_lebih'] - $rk['nilai_kurang'], 2);
        $rk['akurasi'] = $final ? ($o->akurasi !== null ? (float) $o->akurasi : null) : ($rk['dihitung'] ? round($rk['cocok'] / $rk['dihitung'] * 100, 2) : null);
        if (! array_key_exists('akurasi_nilai', $rk) || ! $final) {
            $rk['akurasi_nilai'] = $rk['nilai_sistem'] > 0 ? round(max(0, 1 - ($rk['nilai_kurang'] + $rk['nilai_lebih']) / $rk['nilai_sistem']) * 100, 2) : null;
        }

        return [
            'o' => $o, 'final' => $final, 'baris' => $baris, 'dikeluarkan' => $keluar, 'foto' => $foto, 'ringkasan' => $rk,
            'penghitung' => $o->penghitung->pluck('name')->merge($rows->pluck('penghitung.name'))->filter()->unique()->values()->all(),
        ];
    }

    private function hargaUntukAkurasi(StokOpnameItem $r, float $harga): float
    {
        return $harga > 0 ? $harga : $this->stok->hargaPokokRataRata($r->item_id, $r->opname->cabang_id);
    }

    /**
     * Angka merah di menu: penyetuju = opname menunggu persetujuan; penghitung =
     * opname yang sedang menunggu dia hitung. Aman sebelum migration (cek tabel).
     */
    public static function badgeMenu(): int
    {
        $user = auth()->user();
        if (! $user || ! \Illuminate\Support\Facades\Schema::hasTable('stok_opnames')) {
            return 0;
        }
        $lokasi = fn ($q) => $user->canAccessAllBranches() ? $q : $q->whereIn('cabang_id', $user->cabangs()->pluck('cabangs.id'));

        if ($user->hasPermission('stok_opname.setujui')) {
            return $lokasi(StokOpname::where('status', StokOpname::DIAJUKAN))->count();
        }

        return $lokasi(StokOpname::where('status', StokOpname::MENGHITUNG)
            ->whereHas('penghitung', fn ($q) => $q->where('users.id', $user->id)))->count();
    }

    // ===================== Notifikasi =====================

    /** User yang boleh menyetujui opname di lokasi ini (termasuk Owner). */
    public function penyetujuUntuk(StokOpname $o): Collection
    {
        return User::where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission('stok_opname.setujui') && self::bolehAksesLokasi($u, $o->cabang_id))
            ->values();
    }

    private function kabari(iterable $users, StokOpname $o, string $judul, string $pesan, string $warna): void
    {
        try {
            NotificationService::send($users, new StokOpnameNotification($judul, $pesan, route('stok-opname.show', $o), $warna));
        } catch (\Throwable $e) {
            \Log::warning('Gagal kirim notifikasi stok opname: ' . $e->getMessage());
        }
    }
}
