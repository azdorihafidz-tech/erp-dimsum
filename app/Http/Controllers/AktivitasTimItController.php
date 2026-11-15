<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Keamanan → Aktivitas Tim IT: semua login & tindakan akun Tim IT di instalasi
 * ini, untuk Owner (dan Tim IT). Hanya-baca — tidak ada tombol hapus.
 */
class AktivitasTimItController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->aksesPenuh(), 403);
        $dari = $request->filled('dari') ? Carbon::parse($request->input('dari'))->startOfDay() : today()->subDays(30);
        $sampai = $request->filled('sampai') ? Carbon::parse($request->input('sampai'))->endOfDay() : now()->endOfDay();

        $log = Activity::with('causer')->where('log_name', 'TimIT')
            ->whereBetween('created_at', [$dari, $sampai])
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('tim-it.aktivitas', compact('log', 'dari', 'sampai'));
    }
}
