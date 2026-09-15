<?php

namespace App\Http\Controllers;

use App\Models\PengaturanUmum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PengaturanUmumController extends Controller
{
    public function edit()
    {
        abort_unless(auth()->user()->can('pengaturan.umum_lihat'), 403);

        $setting = PengaturanUmum::firstOrCreate([], [
            'nama_perusahaan'   => "D'mentai",
            'logo_path'         => null,
            'alamat_perusahaan' => null,
        ]);

        return view('pengaturan.umum', compact('setting'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->can('pengaturan.umum_edit'), 403);

        $validated = $request->validate([
            'nama_perusahaan'   => ['required', 'string', 'max:100'],
            'alamat_perusahaan' => ['nullable', 'string', 'max:1000'],
            'logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        $setting = PengaturanUmum::firstOrCreate([], [
            'nama_perusahaan' => "D'mentai",
        ]);

        $setting->nama_perusahaan   = $validated['nama_perusahaan'];
        $setting->alamat_perusahaan = $validated['alamat_perusahaan'] ?? null;

        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            // Hapus logo lama jika ada
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $path = $request->file('logo')->store('logo', 'public');
            $setting->logo_path = $path;
        }

        if ($request->boolean('hapus_logo') && $setting->logo_path) {
            if (Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = null;
        }

        $setting->save();

        activity()
            ->performedOn($setting)
            ->log('Update pengaturan umum perusahaan');

        return redirect()
            ->route('pengaturan.umum')
            ->with('success', 'Pengaturan umum berhasil disimpan.');
    }
}
