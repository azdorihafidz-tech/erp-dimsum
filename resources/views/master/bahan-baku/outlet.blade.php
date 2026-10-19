@extends('layouts.app')

@section('title', 'Harga & Stok di ' . $outlet->nama_cabang . ' — ' . $item->nama_item)

@php
    $fmtAngka = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $adaPack = $item->hasUnitBeli();
    $standar = (float) ($item->harga_beli_terakhir ?? 0);
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-shop me-2 text-primary"></i>Harga &amp; Stok — {{ $item->nama_item }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.bahan-baku.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="bahan-baku" />
    </div>
</div>

<x-pilih-outlet :outlet="$outlet" :pilihan="$pilihanOutlet" :action="route('master.bahan-baku.outlet', $item)" />

<form method="POST" action="{{ route('master.bahan-baku.outlet.update', $item) }}" id="formHargaOutlet">
@csrf
@method('PUT')
<input type="hidden" name="cabang_id" value="{{ $outlet->id }}">
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Harga Beli di {{ $outlet->nama_cabang }} <x-tooltip key="master_bahan_baku.harga_cabang" /></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Harga per {{ $item->satuan }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">Rp</span>
                            <input type="number" step="0.01" min="0" name="harga_beli" id="hargaPcs" class="form-control @error('harga_beli') is-invalid @enderror"
                                value="{{ old('harga_beli', $fmtAngka($hargaOutlet)) }}" placeholder="{{ $fmtAngka($standar) ?: '0' }}">
                        </div>
                        @error('harga_beli')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    @if($adaPack)
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">atau Harga per {{ $item->unit_beli }} <span class="text-muted fw-normal">(isi {{ $fmtAngka($item->isi_per_unit_beli) }} {{ $item->satuan }})</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">Rp</span>
                            <input type="number" step="1" min="0" id="hargaPack" class="form-control" autocomplete="off"
                                placeholder="{{ $standar ? round($standar * (float) $item->isi_per_unit_beli) : '0' }}">
                        </div>
                    </div>
                    @endif
                </div>
                <div class="form-text mt-2">
                    Kosongkan = memakai <strong>Harga Standar</strong> (Rp {{ number_format($standar, 0, ',', '.') }} / {{ $item->satuan }}).
                    Harga ini dipakai sebagai harga awal saat {{ $outlet->nama_cabang }} membuat PO dan untuk perkiraan HPP produk di outlet ini.
                    @if($adaPack) Isi salah satu kolom — kolom lainnya terhitung otomatis. @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Stok di {{ $outlet->nama_cabang }}</div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Qty Minimum <x-tooltip key="master_bahan_baku.pengaturan_outlet" /></label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0" name="qty_minimum" class="form-control @error('qty_minimum') is-invalid @enderror"
                                value="{{ old('qty_minimum', $stok ? $fmtAngka($stok->qty_minimum) : '') }}"
                                placeholder="{{ $fmtAngka($item->qty_minimum) ?: '0' }}">
                            <span class="input-group-text">{{ $item->satuan }}</span>
                        </div>
                        @error('qty_minimum')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="small text-muted">Stok sekarang</div>
                        <div class="fs-5 fw-bold">{{ $stok ? fmt_qty($stok->qty) : '0' }} <span class="fs-6 fw-normal text-muted">{{ $item->satuan }}</span></div>
                    </div>
                </div>
                <div class="form-text mt-2">Muncul peringatan "stok menipis" kalau stok di outlet ini sama atau di bawah angka ini. Isi 0 = tanpa peringatan.</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan untuk {{ $outlet->nama_cabang }}</button>
                <a href="{{ route('master.bahan-baku.edit', $item) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Ubah Data Barang (nama, satuan, Harga Standar)</a>
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@if($adaPack)
@push('scripts')
<script>
(function () {
    const isi = {{ (float) $item->isi_per_unit_beli }};
    const pcs = document.getElementById('hargaPcs');
    const pack = document.getElementById('hargaPack');
    const dariPcs = () => { pack.value = pcs.value !== '' ? Math.round(parseFloat(pcs.value) * isi) : ''; };
    pcs.addEventListener('input', dariPcs);
    pack.addEventListener('input', () => { pcs.value = pack.value !== '' ? Math.round(parseFloat(pack.value) / isi * 100) / 100 : ''; });
    dariPcs();
})();
</script>
@endpush
@endif
