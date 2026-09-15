<?php

namespace App\Http\Controllers;

use App\Enums\TipeCabang;
use App\Http\Requests\CabangRequest;
use App\Models\Absensi;
use App\Models\AbsenDevice;
use App\Models\Asset;
use App\Models\BepReport;
use App\Models\BepSetting;
use App\Models\Cabang;
use App\Models\Cuti;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\FaceAttendance;
use App\Models\Kas;
use App\Models\Karyawan;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Penggajian;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

class CabangController extends Controller
{
    /**
     * Daftar semua cabang
     */
    public function index(Request $request)
    {
        $query = Cabang::with('kepalaCabang')
            ->withCount('karyawans');

        // Filter by tipe
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        // Search
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_cabang', 'like', "%{$keyword}%")
                  ->orWhere('kode_cabang', 'like', "%{$keyword}%")
                  ->orWhere('alamat', 'like', "%{$keyword}%");
            });
        }

        $cabangs = $query->orderBy('tipe')->orderBy('nama_cabang')->paginate(15)->withQueryString();

        $stats = [
            'total'        => Cabang::count(),
            'aktif'        => Cabang::where('is_active', true)->count(),
            'cabang'       => Cabang::where('tipe', TipeCabang::Cabang)->count(),
            'gudang_pusat' => Cabang::where('tipe', TipeCabang::GudangPusat)->count(),
            'head_office'  => Cabang::where('tipe', TipeCabang::HeadOffice)->count(),
        ];

        return view('cabang.index', compact('cabangs', 'stats'));
    }

    /**
     * Form tambah cabang baru
     */
    public function create()
    {
        $users = User::where('is_active', true)
            ->whereIn('role', ['manajer_cabang', 'admin_gudang', 'owner', 'admin_pusat'])
            ->orderBy('name')
            ->get();

        $tipes = TipeCabang::cases();

        return view('cabang.create', compact('users', 'tipes'));
    }

    /**
     * Simpan cabang baru
     */
    public function store(CabangRequest $request)
    {
        $data = $request->validated();
        $data['kode_cabang'] = strtoupper($data['kode_cabang']);
        $data['is_active'] = true;

        $cabang = Cabang::create($data);

        // Auto-create 3 shift default untuk cabang baru
        $defaultShifts = [
            ['nama_shift' => 'Shift 1 (Pagi)',  'jam_masuk' => '08:00:00', 'jam_keluar' => '16:00:00'],
            ['nama_shift' => 'Shift 2 (Sore)',  'jam_masuk' => '16:00:00', 'jam_keluar' => '00:00:00'],
            ['nama_shift' => 'Shift 3 (Malam)', 'jam_masuk' => '00:00:00', 'jam_keluar' => '08:00:00'],
        ];
        foreach ($defaultShifts as $shiftData) {
            Shift::create([
                'cabang_id'             => $cabang->id,
                'nama_shift'            => $shiftData['nama_shift'],
                'jam_masuk'             => $shiftData['jam_masuk'],
                'jam_keluar'            => $shiftData['jam_keluar'],
                'toleransi_telat_menit' => 15,
                'is_active'             => true,
            ]);
        }

        // Jika ada kepala cabang, auto-assign ke cabang ini
        if ($cabang->kepala_cabang_id) {
            $user = User::find($cabang->kepala_cabang_id);
            if ($user && !$user->cabangs()->where('cabangs.id', $cabang->id)->exists()) {
                $user->cabangs()->attach($cabang->id, ['is_default' => false]);
            }
        }

        return redirect()
            ->route('cabang.index')
            ->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil ditambahkan.");
    }

    /**
     * Detail cabang (opsional — redirect ke edit)
     */
    public function show(Cabang $cabang)
    {
        return redirect()->route('cabang.edit', $cabang);
    }

    /**
     * Form edit cabang
     */
    public function edit(Cabang $cabang)
    {
        $users = User::where('is_active', true)
            ->whereIn('role', ['manajer_cabang', 'admin_gudang', 'owner', 'admin_pusat'])
            ->orderBy('name')
            ->get();

        $tipes = TipeCabang::cases();

        // User yang sudah assign ke cabang ini
        $assignedUsers = $cabang->users()->get();

        // Shifts cabang ini
        $shifts = $cabang->shifts;

        return view('cabang.edit', compact('cabang', 'users', 'tipes', 'assignedUsers', 'shifts'));
    }

    /**
     * Update cabang
     */
    public function update(CabangRequest $request, Cabang $cabang)
    {
        $data = $request->validated();
        $data['kode_cabang'] = strtoupper($data['kode_cabang']);

        $oldKepalaCabangId = $cabang->kepala_cabang_id;
        $cabang->update($data);

        // Jika kepala cabang berubah, assign kepala baru ke cabang ini
        if ($data['kepala_cabang_id'] && $data['kepala_cabang_id'] != $oldKepalaCabangId) {
            $user = User::find($data['kepala_cabang_id']);
            if ($user && !$user->cabangs()->where('cabangs.id', $cabang->id)->exists()) {
                $user->cabangs()->attach($cabang->id, ['is_default' => false]);
            }
        }

        // Update shifts jika ada data shift dari form
        foreach ($request->input('shifts', []) as $shiftData) {
            if (empty($shiftData['id'])) continue;
            Shift::where('id', (int) $shiftData['id'])
                ->where('cabang_id', $cabang->id)
                ->update([
                    'nama_shift'            => $shiftData['nama_shift'] ?? 'Shift',
                    'jam_masuk'             => $shiftData['jam_masuk'] ?? '08:00',
                    'jam_keluar'            => $shiftData['jam_keluar'] ?? '16:00',
                    'toleransi_telat_menit' => (int) ($shiftData['toleransi_telat_menit'] ?? 15),
                    'is_active'             => isset($shiftData['is_active']) ? 1 : 0,
                ]);
        }

        return redirect()
            ->route('cabang.index')
            ->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil diperbarui.");
    }

    /**
     * Hapus cabang beserta seluruh data terkait (soft-delete cascade).
     * Semua data terkait (karyawan, stok, absensi, dll) ikut di-soft-delete dalam satu transaksi.
     */
    public function destroy(Cabang $cabang, CascadeDeleteService $cascadeService)
    {
        $nama = $cabang->nama_cabang;

        try {
            $deleted = $cascadeService->deleteCabangCascade($cabang);

            $labels = [
                'karyawan'           => 'Karyawan',
                'penggajian'         => 'Penggajian',
                'absensi'            => 'Absensi',
                'face_attendance'    => 'Absensi Wajah',
                'absen_device'       => 'Device Absen',
                'order'              => 'Order',
                'purchase_order'     => 'Purchase Order',
                'stok'               => 'Stok',
                'stock_request'      => 'Permintaan Stok',
                'stock_transfer'     => 'Transfer Stok',
                'transaksi_keuangan' => 'Transaksi Keuangan',
                'aset'               => 'Aset',
                'shift'              => 'Shift',
                'hari_libur'         => 'Hari Libur',
                'cabang'             => 'Cabang',
            ];

            $ringkasan = collect($deleted)
                ->map(fn($count, $key) => "{$count} " . ($labels[$key] ?? $key))
                ->filter()
                ->join(', ');

            return redirect()
                ->route('cabang.index')
                ->with('success', "Cabang <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");

        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus cabang <strong>{$nama}</strong>: " . $e->getMessage());
        }
    }

    /**
     * Toggle aktif / nonaktif cabang
     */
    public function toggleAktif(Cabang $cabang)
    {
        $cabang->update(['is_active' => !$cabang->is_active]);

        $status = $cabang->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil {$status}.");
    }

    /**
     * Kelola user yang di-assign ke cabang ini
     */
    public function assignUser(Request $request, Cabang $cabang)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'is_default' => 'sometimes|boolean',
        ]);

        $userId = $request->user_id;
        $isDefault = $request->boolean('is_default', false);

        if ($cabang->users()->where('users.id', $userId)->exists()) {
            return back()->with('warning', 'User sudah terdaftar di cabang ini.');
        }

        $cabang->users()->attach($userId, ['is_default' => $isDefault]);

        $user = User::find($userId);
        return back()->with('success', "<strong>{$user->name}</strong> berhasil ditambahkan ke cabang ini.");
    }

    /**
     * Hapus user dari cabang
     */
    public function removeUser(Request $request, Cabang $cabang)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $cabang->users()->detach($request->user_id);

        return back()->with('success', 'User berhasil dihapus dari cabang ini.');
    }
}
