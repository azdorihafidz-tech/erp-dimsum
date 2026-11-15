<?php

namespace App\Services;

use App\Enums\RoleUser;
use App\Models\Cabang;
use App\Models\StokOpname;
use App\Models\User;
use App\Notifications\StokOpnameNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengingat Stok Opname akhir bulan (RANCANGAN_STOK_OPNAME.md bagian 7).
 * Masa pengingat = H-N sebelum akhir bulan (Pengaturan Umum) mulai jam yang
 * diatur. Lokasi yang BELUM punya opname (selain dibatalkan) bulan itu →
 * notifikasi ke kepala lokasi (kalau kosong: Manajer Cabang lokasi itu) + Owner,
 * satu kali per orang per hari (dicek dari tabel notifikasi, bukan cache).
 * Dipicu saat aplikasi dibuka (AppServiceProvider, sekali per jam) dan
 * Schedule::call() sebagai cadangan — tanpa proc_open/exec.
 */
class StokOpnamePengingat
{
    public const JUDUL = 'Pengingat Stok Opname';

    public static function siap(): bool
    {
        return Schema::hasTable('stok_opnames') && Schema::hasColumn('pengaturan_umums', 'opname_pengingat_aktif');
    }

    /** Sedang masa pengingat? (hari ≥ akhir bulan − H, dan jam ≥ jam pengingat) */
    public static function dalamMasa(?Carbon $kini = null): bool
    {
        if (! self::siap()) {
            return false;
        }
        if (! \App\Support\Paket::aktif('stok_opname') || \App\Support\Paket::bacaSaja($kini)) {
            return false;
        }
        $s = StokOpnameService::setting();
        if (! $s->opname_pengingat_aktif) {
            return false;
        }
        $kini ??= now();
        $mulai = $kini->copy()->endOfMonth()->startOfDay()->subDays(max(1, (int) $s->opname_pengingat_hari));
        [$jam, $menit] = array_map('intval', explode(':', (string) ($s->opname_pengingat_jam ?: '08:00')) + [0, 0]);

        return $kini->greaterThanOrEqualTo($mulai) && ($kini->hour * 60 + $kini->minute) >= ($jam * 60 + $menit);
    }

    /** Lokasi aktif (dari daftar) yang belum punya opname bulan ini (selain dibatalkan). */
    public static function lokasiBelum(Collection $cabangIds, ?Carbon $kini = null): Collection
    {
        $kini ??= now();
        $sudah = StokOpname::whereIn('cabang_id', $cabangIds)->where('status', '<>', StokOpname::DIBATALKAN)
            ->whereBetween('created_at', [$kini->copy()->startOfMonth(), $kini->copy()->endOfMonth()])
            ->pluck('cabang_id')->unique();

        return Cabang::where('is_active', true)->whereIn('id', $cabangIds)->whereNotIn('id', $sudah)->orderBy('nama_cabang')->get();
    }

    /** Penerima untuk satu lokasi: kepala lokasi (atau Manajer Cabang lokasi itu) + Owner. */
    public static function penerima(Cabang $c): Collection
    {
        $kepala = $c->kepala_cabang_id ? User::where('id', $c->kepala_cabang_id)->where('is_active', true)->get() : collect();
        if ($kepala->isEmpty()) {
            $kepala = User::where('is_active', true)->where('role', RoleUser::ManajerCabang->value)
                ->whereHas('cabangs', fn ($q) => $q->where('cabangs.id', $c->id))->get();
        }

        return $kepala->merge(User::where('is_active', true)->where('role', RoleUser::Owner->value)->get())->unique('id')->values();
    }

    /** @return int jumlah notifikasi yang dikirim */
    public function jalankan(?Carbon $kini = null): int
    {
        $kini ??= now();
        if (! self::dalamMasa($kini)) {
            return 0;
        }
        $belum = self::lokasiBelum(Cabang::where('is_active', true)->pluck('id'), $kini);
        if ($belum->isEmpty()) {
            return 0;
        }

        $perUser = []; // user_id => [User, nama lokasi[]]
        foreach ($belum as $c) {
            foreach (self::penerima($c) as $u) {
                $perUser[$u->id] ??= [$u, []];
                $perUser[$u->id][1][] = $c->nama_cabang;
            }
        }

        $terkirim = 0;
        $sisaHari = (int) $kini->copy()->startOfDay()->diffInDays($kini->copy()->endOfMonth()->startOfDay());
        foreach ($perUser as [$u, $lokasi]) {
            $sudahHariIni = DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $u->id)
                ->where('type', StokOpnameNotification::class)->whereDate('created_at', now()->toDateString())
                ->where('data', 'like', '%' . self::JUDUL . '%')->exists();
            if ($sudahHariIni) {
                continue;
            }
            NotificationService::send([$u], new StokOpnameNotification(self::JUDUL,
                'Belum ada stok opname bulan ini di: ' . implode(', ', $lokasi) . '. ' . ($sisaHari > 0 ? "Bulan berakhir {$sisaHari} hari lagi." : 'Hari ini akhir bulan.'),
                route('stok-opname.dashboard'), 'danger'));
            $terkirim++;
        }

        return $terkirim;
    }
}
