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
                    <span class="fw-semibold">Identitas Usaha</span>
                </div>
                <div class="card-body p-4">

                    <div class="mb-3">
                        <label for="nama_perusahaan" class="form-label fw-medium">
                            Nama Usaha <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan"
                            class="form-control @error('nama_perusahaan') is-invalid @enderror"
                            value="{{ old('nama_perusahaan', $setting->nama_perusahaan) }}"
                            maxlength="100" required>
                        <div class="form-text">Nama lengkap usaha — tampil di TV Display Antrian dan aplikasi kasir.</div>
                        @error('nama_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="nama_singkat" class="form-label fw-medium">
                                Nama Singkat <x-tooltip key="pengaturan_umum.nama_singkat" placement="top" />
                            </label>
                            <input type="text" id="nama_singkat" name="nama_singkat"
                                class="form-control @error('nama_singkat') is-invalid @enderror"
                                value="{{ old('nama_singkat', $setting->nama_singkat) }}"
                                maxlength="30" placeholder="Kosong = pakai Nama Usaha">
                            @error('nama_singkat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="tagline" class="form-label fw-medium">
                                Tagline <x-tooltip key="pengaturan_umum.tagline" placement="top" />
                            </label>
                            <input type="text" id="tagline" name="tagline"
                                class="form-control @error('tagline') is-invalid @enderror"
                                value="{{ old('tagline', $setting->tagline) }}"
                                maxlength="150" placeholder="Contoh: Dimsum & Gyoza">
                            @error('tagline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_perusahaan" class="form-label fw-medium">Alamat</label>
                        <textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="3"
                            class="form-control @error('alamat_perusahaan') is-invalid @enderror"
                            maxlength="1000"
                            placeholder="Jl. Contoh No. 1, Kota...">{{ old('alamat_perusahaan', $setting->alamat_perusahaan) }}</textarea>
                        @error('alamat_perusahaan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="telepon" class="form-label fw-medium">No. Telepon / WhatsApp</label>
                        <input type="text" id="telepon" name="telepon"
                            class="form-control @error('telepon') is-invalid @enderror"
                            value="{{ old('telepon', $setting->telepon) }}"
                            maxlength="30" placeholder="Contoh: 0812-3456-7890">
                        <div class="form-text">Tampil di header laporan PDF (kosong = tidak ditampilkan).</div>
                        @error('telepon')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-3 mb-0">
                        <label for="tanggal_mulai_periode" class="form-label fw-medium">
                            Tanggal Mulai Periode Bulanan (Closing)
                            <x-tooltip key="pengaturan_umum.tanggal_mulai_periode" />
                        </label>
                        @php $tglMulai = (int) old('tanggal_mulai_periode', $setting->tanggal_mulai_periode ?? 1); @endphp
                        <select id="tanggal_mulai_periode" name="tanggal_mulai_periode"
                            class="form-select @error('tanggal_mulai_periode') is-invalid @enderror" style="max-width:220px">
                            @for($d = 1; $d <= 28; $d++)
                            <option value="{{ $d }}" @selected($tglMulai === $d)>Tanggal {{ $d }}</option>
                            @endfor
                        </select>
                        <div class="form-text">
                            Dipakai menu Closing / Tutup Buku. Contoh: pilih <strong>26</strong> = periode "Oktober" adalah
                            26 September s/d 25 Oktober. Pilih <strong>1</strong> = bulan kalender biasa.
                        </div>
                        @error('tanggal_mulai_periode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header py-3 px-4">
                    <i class="bi bi-image me-2 text-primary"></i>
                    <span class="fw-semibold">Logo Usaha</span>
                    <x-tooltip key="pengaturan_umum.logo" placement="top" />
                </div>
                <div class="card-body p-4">

                    <div class="mb-3 p-3 border rounded-3 bg-light d-flex align-items-center gap-3 flex-wrap">
                        <img src="{{ $brand->logoUrl() }}" alt="Logo saat ini" id="logoPreview"
                             style="max-height:80px;max-width:200px;object-fit:contain;">
                        <img src="{{ $brand->faviconUrl() }}" alt="Ikon aplikasi"
                             style="width:48px;height:48px;object-fit:contain;border-radius:10px;background:#fff;border:1px solid #e2e8f0">
                        <div>
                            @if($brand->adaLogoUpload())
                                <div class="fw-medium" style="font-size:0.875rem">Logo aktif</div>
                                <div class="text-muted" style="font-size:0.75rem">Kiri: logo · Kanan: ikon tab browser &amp; aplikasi</div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="hapus_logo" id="hapusLogo" value="1">
                                    <label class="form-check-label text-danger" for="hapusLogo" style="font-size:0.8rem">
                                        Hapus logo ini (kembali ke logo bawaan)
                                    </label>
                                </div>
                            @else
                                <div class="fw-medium" style="font-size:0.875rem">Logo bawaan program</div>
                                <div class="text-muted" style="font-size:0.75rem">Upload logo usaha Anda supaya tampil di semua halaman.</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="logo" class="form-label fw-medium">
                            {{ $brand->adaLogoUpload() ? 'Ganti Logo' : 'Upload Logo' }}
                        </label>
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                            class="form-control @error('logo') is-invalid @enderror"
                            onchange="previewLogo(this)">
                        <div class="form-text">Format: PNG, JPG, WebP. Maksimal 2MB. Paling bagus: PNG latar transparan, bentuk persegi atau bulat. Ikon tab browser &amp; aplikasi dibuat otomatis dari logo ini.</div>
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
                    <span class="fw-semibold">Dipakai di mana?</span>
                </div>
                <div class="card-body p-4" style="font-size:0.8rem">
                    <p class="text-muted mb-2">
                        <i class="bi bi-box-arrow-in-right me-1 text-primary"></i>
                        <strong>Login, sidebar &amp; judul tab</strong> — logo, nama singkat &amp; tagline.
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-printer me-1 text-primary"></i>
                        <strong>Struk &amp; laporan PDF</strong> — nama singkat &amp; tagline (footer struk per cabang diatur di Cabang → Edit).
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-tv me-1 text-primary"></i>
                        <strong>TV Display Antrian</strong> — logo &amp; nama usaha.
                    </p>
                    <p class="text-muted mb-0">
                        <i class="bi bi-phone me-1 text-primary"></i>
                        <strong>Ikon aplikasi (PWA) &amp; aplikasi kasir</strong> — ikon dibuat otomatis dari logo.
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
    if (input.files && input.files[0] && preview) {
        const reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
