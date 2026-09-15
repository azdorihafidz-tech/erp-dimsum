@extends('layouts.app')

@section('title', 'Pengaturan Umum')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">
            <i class="bi bi-building me-2 text-primary"></i>Pengaturan Umum
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Pengaturan Umum</li>
            </ol>
        </nav>
    </div>
    <x-panduan-button slug="pengaturan-umum" />
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('pengaturan.umum.update') }}" enctype="multipart/form-data" id="formPengaturanUmum">
    @csrf @method('PUT')

    <div class="row g-4">
        {{-- Kolom Kiri --}}
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <span class="fw-semibold">Identitas Perusahaan</span>
                </div>
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="nama_perusahaan" class="form-label fw-medium">
                            Nama Perusahaan <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan"
                            class="form-control @error('nama_perusahaan') is-invalid @enderror"
                            value="{{ old('nama_perusahaan', $setting->nama_perusahaan) }}"
                            maxlength="100" required>
                        <div class="form-text">Nama ini tampil di header TV Display Antrian dan laporan.</div>
                        @error('nama_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="alamat_perusahaan" class="form-label fw-medium">Alamat Perusahaan</label>
                        <textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="3"
                            class="form-control @error('alamat_perusahaan') is-invalid @enderror"
                            maxlength="1000"
                            placeholder="Jl. Contoh No. 1, Kota...">{{ old('alamat_perusahaan', $setting->alamat_perusahaan) }}</textarea>
                        @error('alamat_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-image me-2 text-primary"></i>
                    <span class="fw-semibold">Logo Perusahaan</span>
                </div>
                <div class="card-body p-4">

                    {{-- Preview logo saat ini --}}
                    @if($setting->logo_path)
                    <div class="mb-3 p-3 border rounded-3 bg-light d-flex align-items-center gap-3">
                        <img src="{{ url('/img/' . $setting->logo_path) }}"
                             alt="Logo saat ini" id="logoPreview"
                             style="max-height:80px;max-width:200px;object-fit:contain;">
                        <div>
                            <div class="fw-medium" style="font-size:0.875rem">Logo aktif</div>
                            <div class="text-muted" style="font-size:0.75rem">{{ basename($setting->logo_path) }}</div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="hapus_logo" id="hapusLogo" value="1">
                                <label class="form-check-label text-danger" for="hapusLogo" style="font-size:0.8rem">
                                    Hapus logo ini
                                </label>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="mb-3 p-3 border border-dashed rounded-3 text-center text-muted" id="logoPlaceholder">
                        <i class="bi bi-image fs-2 d-block mb-1"></i>
                        <span style="font-size:0.875rem">Belum ada logo</span>
                        {{-- Preview sebelum upload --}}
                        <img src="" alt="Preview" id="logoPreview"
                             style="display:none;max-height:80px;max-width:200px;object-fit:contain;margin:0 auto;display:none">
                    </div>
                    @endif

                    <div>
                        <label for="logo" class="form-label fw-medium">
                            {{ $setting->logo_path ? 'Ganti Logo' : 'Upload Logo' }}
                        </label>
                        <input type="file" id="logo" name="logo" accept="image/*"
                            class="form-control @error('logo') is-invalid @enderror"
                            onchange="previewLogo(this)">
                        <div class="form-text">Format: JPG, PNG, SVG, WebP. Maksimal 2MB. Rekomendasi: logo transparan (PNG/SVG) ukuran 200×80px.</div>
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- Kolom Kanan --}}
        <div class="col-12 col-lg-4">

            <div class="card mb-3">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-info-circle me-2 text-muted"></i>
                    <span class="fw-semibold">Info</span>
                </div>
                <div class="card-body p-4" style="font-size:0.8rem">
                    <p class="text-muted mb-2">
                        <i class="bi bi-tv me-1 text-primary"></i>
                        <strong>TV Display Antrian</strong> — Logo dan nama perusahaan tampil di header layar antrian setiap cabang.
                    </p>
                    <p class="text-muted mb-0">
                        <i class="bi bi-printer me-1 text-primary"></i>
                        <strong>Struk & Laporan</strong> — Nama perusahaan tampil di header struk POS.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-2"></i>Simpan Pengaturan
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Batal
                    </a>
                </div>
            </div>

        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const placeholder = document.getElementById('logoPlaceholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.border = '1px solid #3b82f6';
                placeholder.style.background = '#eff6ff';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
