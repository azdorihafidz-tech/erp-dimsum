@extends('layouts.app')

@section('title', 'Edit ' . $item->nama_item)

@section('content')

<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit — {{ $item->nama_item }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<form method="POST" action="{{ route('master.bahan-baku.update', $item) }}">
@csrf
@method('PUT')
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kode Item *</label>
                        <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                            style="text-transform:uppercase" value="{{ old('kode_item', $item->kode_item) }}" required>
                        @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold">Nama Item *</label>
                        <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                            value="{{ old('nama_item', $item->nama_item) }}" required>
                        @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Tipe *</label>
                        <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                            <option value="bahan_baku" @selected(old('tipe',$item->tipe)=='bahan_baku')>Bahan Baku</option>
                            <option value="kemasan" @selected(old('tipe',$item->tipe)=='kemasan')>Kemasan</option>
                            <option value="tambahan_gratis" @selected(old('tipe',$item->tipe)=='tambahan_gratis')>Tambahan Gratis (add-on POS)</option>
                        </select>
                        @error('tipe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Unit / Satuan *</label>
                        <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                            value="{{ old('satuan', $item->satuan) }}" required>
                        @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <select name="item_category_id" class="form-select">
                            <option value="">— Pilih —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('item_category_id',$item->item_category_id)==$cat->id)>{{ $cat->nama_kategori }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Harga &amp; Stok Minimum</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Harga Beli / HPP</label>
                        <x-input-rupiah name="harga_beli_terakhir" :value="old('harga_beli_terakhir', $item->harga_beli_terakhir)" />
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Qty Minimum</label>
                        <input type="number" name="qty_minimum" class="form-control" min="0" step="0.001" value="{{ old('qty_minimum', $item->qty_minimum) }}">
                    </div>
                </div>
                <div class="form-text mt-2">Stok per outlet dikelola lewat menu <a href="{{ route('stok.index') }}">Stok &amp; Inventori</a> / Adjustment Stok.</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                    <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>

        @can('master.bahan_baku.delete')
        <div class="card mt-3 border-danger">
            <div class="card-body">
                <h6 class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle me-1"></i>Zona Berbahaya</h6>
                <button type="submit" form="formHapusBahanBaku" class="btn btn-outline-danger btn-sm w-100"
                    onclick="return confirm('Hapus {{ addslashes($item->nama_item) }} secara permanen?')">
                    <i class="bi bi-trash me-1"></i>Hapus Item
                </button>
            </div>
        </div>
        @endcan
    </div>
</div>
</form>

{{--
    Tahap 7 D'mentai (Bug 3 fix, 2026-09-14) — form hapus WAJIB sibling dari
    form utama, BUKAN nested (lihat penjelasan lengkap di
    master/produk-jual/_form.blade.php). Nested <form> di sini adalah
    penyebab bug "klik Simpan Perubahan malah menghapus item".
--}}
@can('master.bahan_baku.delete')
<form method="POST" id="formHapusBahanBaku" action="{{ route('master.bahan-baku.destroy', $item) }}">
    @csrf @method('DELETE')
</form>
@endcan

@endsection
