@extends('layouts.app')

@section('title', 'Harga di ' . $outlet->nama_cabang . ' — ' . $item->nama_item)

@php
    $hargaUmum = (float) ($item->harga_jual ?? 0);
    $hargaOutlet = $row?->harga_override !== null ? (int) round((float) $row->harga_override) : null;
    $varianKhusus = $item->punya_varian ? $item->variants->whereNotNull('harga_override')->count() : 0;
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-shop me-2 text-primary"></i>Harga Jual — {{ $item->nama_item }}</h5>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('master.produk-jual.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        <x-panduan-button slug="produk-jual" />
    </div>
</div>

<x-pilih-outlet :outlet="$outlet" :pilihan="$pilihanOutlet" :action="route('master.produk-jual.outlet', $item)" />

<form method="POST" action="{{ route('master.produk-jual.outlet.update', $item) }}">
@csrf
@method('PUT')
<input type="hidden" name="cabang_id" value="{{ $outlet->id }}">
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Penjualan di {{ $outlet->nama_cabang }} <x-tooltip key="master_produk_jual.cabang_aktif" /></div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="dijual" value="1" id="dijual" @checked(old('dijual', $dijual))>
                    <label class="form-check-label fw-semibold" for="dijual">Dijual di {{ $outlet->nama_cabang }} (tampil di POS outlet ini)</label>
                </div>

                <label class="form-label small fw-semibold">Harga Jual di {{ $outlet->nama_cabang }}</label>
                <div class="input-group" style="max-width:320px">
                    <span class="input-group-text bg-white text-muted">Rp</span>
                    <input type="number" step="1" min="0" name="harga_jual" id="hargaJualOutlet" class="form-control @error('harga_jual') is-invalid @enderror"
                        value="{{ old('harga_jual', $hargaOutlet) }}" placeholder="{{ $hargaUmum }}">
                </div>
                @error('harga_jual')<div class="text-danger small">{{ $message }}</div>@enderror
                <div class="form-text">
                    Kosongkan = memakai <strong>Harga Jual Umum</strong> (Rp {{ number_format($hargaUmum, 0, ',', '.') }}).
                    @if($varianKhusus) {{ $varianKhusus }} varian punya harga khusus sendiri — varian itu tetap memakai harga variannya. @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">Perkiraan Untung di {{ $outlet->nama_cabang }} <x-tooltip key="master_produk_jual.hpp_outlet" /></div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="small text-muted">Harga Jual</div>
                        <div class="fs-5 fw-bold" id="tampilHarga">—</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">HPP (modal)</div>
                        <div class="fs-5 fw-bold">Rp {{ number_format($hpp, 0, ',', '.') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Untung / Margin</div>
                        <div class="fs-5 fw-bold" id="tampilUntung">—</div>
                        <div class="small" id="tampilMargin"></div>
                    </div>
                </div>
                <div class="form-text mt-3">
                    HPP dihitung dari resep produk memakai <strong>harga beli bahan di {{ $outlet->nama_cabang }}</strong>
                    (atur di menu Bahan Baku → tombol Harga Outlet). Ini perkiraan untuk menentukan harga — modal riil tiap transaksi tetap dihitung dari stok outlet (FIFO).
                    @if($hpp <= 0) <br><span class="text-warning">Produk ini belum punya resep atau harga bahannya belum diisi, jadi HPP Rp 0.</span> @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan untuk {{ $outlet->nama_cabang }}</button>
                <a href="{{ route('master.produk-jual.edit', $item) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Ubah Data Produk (nama, resep, Harga Umum)</a>
            </div>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const umum = {{ $hargaUmum }}, hpp = {{ (float) $hpp }};
    const input = document.getElementById('hargaJualOutlet');
    const rp = n => 'Rp ' + Math.round(Math.abs(n)).toLocaleString('id-ID');
    function hitung() {
        const harga = input.value !== '' ? (parseFloat(input.value) || 0) : umum;
        const untung = harga - hpp;
        document.getElementById('tampilHarga').textContent = rp(harga);
        const elU = document.getElementById('tampilUntung');
        elU.textContent = (untung < 0 ? '-' : '') + rp(untung);
        elU.className = 'fs-5 fw-bold ' + (untung < 0 ? 'text-danger' : 'text-success');
        document.getElementById('tampilMargin').textContent = harga > 0 ? 'margin ' + (untung / harga * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' : '';
    }
    input.addEventListener('input', hitung);
    hitung();
})();
</script>
@endpush
