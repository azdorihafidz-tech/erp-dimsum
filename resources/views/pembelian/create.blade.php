@extends('layouts.app')

@section('title', $isPembelianLangsung ? 'PO Mendesak/Langsung' : 'Buat Purchase Order')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-bag-plus me-2 text-{{ $isPembelianLangsung ? 'warning' : 'primary' }}"></i>
        {{ $isPembelianLangsung ? 'PO Mendesak / Langsung' : 'Buat Purchase Order' }}
    </h5>
    <a href="{{ route('pembelian.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
</div>

@if($isPembelianLangsung)
<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Pembelian Mendesak/Langsung:</strong> PO ini akan langsung ke cabang tanpa melalui gudang pusat. Butuh approval.
</div>
@endif

<form method="POST" action="{{ route('pembelian.store') }}" id="formPo">
    @csrf
    <input type="hidden" name="pembelian_langsung" value="{{ $isPembelianLangsung ? '1' : '0' }}" id="inputPembelianLangsung">

    <div class="row g-3">
        {{-- Info PO --}}
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">Informasi PO</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span> <x-tooltip key="pembelian.supplier_id" /></label>
                            <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                                <option value="">-- Pilih Supplier --</option>
                                @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" @selected(old('supplier_id') == $sup->id)>
                                    {{ $sup->nama_supplier }} ({{ $sup->kode_supplier }})
                                </option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Tanggal PO <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_po" class="form-control @error('tanggal_po') is-invalid @enderror"
                                value="{{ old('tanggal_po', date('Y-m-d')) }}">
                            @error('tanggal_po')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Lokasi Tujuan <x-tooltip key="pembelian.cabang_id" /></label>
                            <select name="cabang_id" class="form-select">
                                @foreach($cabangs as $c)
                                <option value="{{ $c->id }}" @selected(old('cabang_id', $cabangAktif) == $c->id)>
                                    {{ $c->nama_cabang }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkLangsung"
                                    @checked($isPembelianLangsung)
                                    onchange="toggleLangsung(this.checked)">
                                <label class="form-check-label" for="chkLangsung">
                                    <strong>Pembelian Langsung / Mendesak</strong> <x-tooltip key="pembelian.pembelian_langsung" />
                                </label>
                            </div>
                        </div>

                        <div class="col-12" id="sectionAlasan" style="{{ $isPembelianLangsung ? '' : 'display:none' }}">
                            <label class="form-label fw-semibold">Alasan Mendesak <x-tooltip key="pembelian.alasan_langsung" /></label>
                            <textarea name="alasan_langsung" class="form-control @error('alasan_langsung') is-invalid @enderror"
                                rows="2" placeholder="Jelaskan alasan pembelian langsung...">{{ old('alasan_langsung') }}</textarea>
                            @error('alasan_langsung')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan...">{{ old('catatan') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">Ringkasan</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Total Item</span>
                        <span id="summaryItemCount" class="fw-semibold">0</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Total PO</span>
                        <span id="summaryTotal" class="fw-bold fs-5 text-primary">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Item PO --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Item PO</span>
                    <button type="button" class="btn btn-sm btn-success" onclick="tambahItem()">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Item
                    </button>
                </div>
                <div class="card-body">
                    @error('items')<div class="alert alert-danger py-2 mb-3">{{ $message }}</div>@enderror

                    <div id="itemContainer">
                        {{-- Template item akan diisi JS --}}
                    </div>
                    <div id="emptyItems" class="text-center text-muted py-3">
                        Klik "Tambah Item" untuk menambah barang
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Simpan Purchase Order
            </button>
            <a href="{{ route('pembelian.index') }}" class="btn btn-outline-secondary ms-2">Batal</a>
        </div>
    </div>
</form>

@push('scripts')
@php
$itemsJson = $items->map(function($i) {
    return [
        'id'                  => $i->id,
        'nama_item'           => $i->nama_item,
        'kode_item'           => $i->kode_item,
        'satuan'              => $i->satuan,
        'harga_beli_terakhir' => $i->harga_beli_terakhir ?? 0,
        'total_stok'          => $i->total_stok ?? 0,
        'stok_per_lokasi'     => $i->stok_per_lokasi ?? [],
    ];
})->values()->toArray();
@endphp
<script>
const itemsData = @json($itemsJson);
let itemIndex = 0;

function toggleLangsung(checked) {
    document.getElementById('inputPembelianLangsung').value = checked ? '1' : '0';
    document.getElementById('sectionAlasan').style.display = checked ? '' : 'none';
}

function tambahItem() {
    document.getElementById('emptyItems').style.display = 'none';
    const container = document.getElementById('itemContainer');
    const idx = itemIndex++;

    const options = itemsData.map(i =>
        `<option value="${i.id}" data-harga="${i.harga_beli_terakhir}" data-satuan="${i.satuan}">${i.nama_item} (${i.kode_item})</option>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end mb-3 item-row';
    row.innerHTML = `
        <div class="col-12 col-md-5">
            <label class="form-label small">Barang <span class="text-danger">*</span></label>
            <select name="items[${idx}][item_id]" class="form-select form-select-sm" onchange="onItemChange(this, ${idx})" required>
                <option value="">-- Pilih Barang --</option>
                ${options}
            </select>
        </div>
        <div class="col-5 col-md-2">
            <label class="form-label small">Qty Pesan <span class="text-danger">*</span></label>
            <input type="number" name="items[${idx}][qty_pesan]" class="form-control form-control-sm qty-input"
                step="0.001" min="0.001" value="1" required onchange="hitungSubtotal(${idx})">
        </div>
        <div class="col-7 col-md-3">
            <label class="form-label small">Harga Satuan <span class="text-danger">*</span></label>
            <input type="text" inputmode="numeric" data-rupiah name="items[${idx}][harga_satuan]" class="form-control form-control-sm harga-input"
                value="0" required onchange="hitungSubtotal(${idx})">
        </div>
        <div class="col-9 col-md-1">
            <label class="form-label small">Subtotal</label>
            <div class="form-control form-control-sm bg-light text-muted subtotal-display" id="subtotal_${idx}">Rp 0</div>
        </div>
        <div class="col-3 col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="hapusItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="col-12">
            <div id="stokInfo_${idx}" class="small text-muted"></div>
        </div>
    `;
    container.appendChild(row);
    RupiahFormatter.init(row);
    updateSummary();
}

function onItemChange(selectEl, idx) {
    const opt = selectEl.selectedOptions[0];
    if (!opt || !opt.value) {
        const stokEl = document.getElementById(`stokInfo_${idx}`);
        if (stokEl) stokEl.innerHTML = '';
        return;
    }
    const itemId = parseInt(opt.value);
    const item = itemsData.find(i => i.id === itemId);
    const hargaInput = selectEl.closest('.item-row').querySelector('.harga-input');
    if (hargaInput && item) { hargaInput.value = rupiahFmt(item.harga_beli_terakhir); hargaInput.dispatchEvent(new Event('input')); }

    // Tampilkan stok per lokasi
    const stokEl = document.getElementById(`stokInfo_${idx}`);
    if (stokEl && item) {
        if (item.stok_per_lokasi.length === 0) {
            stokEl.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Belum ada stok di sistem.</span>';
        } else {
            const badges = item.stok_per_lokasi.map(s =>
                `<span class="badge bg-light text-dark border me-1">
                    <i class="bi bi-geo-alt me-1"></i>${s.nama_lokasi}:
                    <strong>${s.qty.toLocaleString('id-ID')} ${s.satuan}</strong>
                </span>`
            ).join('');
            stokEl.innerHTML = `<i class="bi bi-archive me-1 text-info"></i><span class="me-1">Stok saat ini:</span>${badges}`;
        }
    }
    hitungSubtotal(idx);
}

function hitungSubtotal(idx) {
    const row = document.querySelector(`[name="items[${idx}][qty_pesan]"]`)?.closest('.item-row');
    if (!row) return;
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const harga = rupiahParse(row.querySelector('.harga-input').value);
    const subtotal = qty * harga;
    const el = document.getElementById(`subtotal_${idx}`);
    if (el) el.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    updateSummary();
}

function hapusItem(btn) {
    btn.closest('.item-row').remove();
    updateSummary();
    if (!document.querySelectorAll('.item-row').length) {
        document.getElementById('emptyItems').style.display = '';
    }
}

function updateSummary() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const harga = rupiahParse(row.querySelector('.harga-input')?.value);
        total += qty * harga;
    });
    document.getElementById('summaryTotal').textContent = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('summaryItemCount').textContent = document.querySelectorAll('.item-row').length;
}

// Tambah 1 item default
tambahItem();
</script>
@endpush
@endsection
