@extends('layouts.app')

@section('title', 'Stok Opname')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-primary"></i>Stok Opname</h5>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <x-panduan-button slug="stok-opname" />
        <a href="{{ route('stok-opname.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        @if(auth()->user()->hasPermission('stok_opname.ajukan'))
        <a href="{{ route('stok-opname.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Mulai Opname</a>
        @endif
    </div>
</div>

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        @if($lokasi->count() > 1)
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Lokasi</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua lokasi</option>
                @foreach($lokasi as $l)
                <option value="{{ $l->id }}" @selected($filter == $l->id)>{{ $l->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Semua</option>
                <option value="menghitung" @selected(request('status') === 'menghitung')>Sedang dihitung</option>
                <option value="diajukan" @selected(request('status') === 'diajukan')>Menunggu persetujuan</option>
                <option value="disetujui" @selected(request('status') === 'disetujui')>Disetujui</option>
                <option value="dibatalkan" @selected(request('status') === 'dibatalkan')>Dibatalkan</option>
            </select>
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button>
            <a href="{{ route('stok-opname.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="list-group list-group-flush">
    @forelse($opnames as $o)
        <a href="{{ route('stok-opname.show', $o) }}" class="list-group-item list-group-item-action py-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="fw-semibold">{{ $o->nomor }} <span class="badge bg-{{ $o->warnaStatus() }}">{{ $o->labelStatus() }}</span></div>
                    <div class="small text-muted">
                        {{ $o->cabang?->nama_cabang }} · {{ $o->labelCakupan() }} · {{ $o->jumlah_barang }} barang
                        · dimulai {{ $o->created_at->format('d/m/Y H:i') }} oleh {{ $o->pembuat?->name ?? '-' }}
                    </div>
                </div>
                @if($o->status === 'disetujui')
                <div class="text-end small flex-shrink-0">
                    <div>Akurasi <strong>{{ $o->akurasi !== null ? rtrim(rtrim(number_format($o->akurasi, 2, ',', '.'), '0'), ',') . '%' : '—' }}</strong></div>
                    @php $bersih = (float) ($o->ringkasan['nilai_bersih'] ?? 0); @endphp
                    <div class="{{ $bersih < 0 ? 'text-danger' : 'text-success' }}">{{ $bersih < 0 ? '−' : '+' }}Rp {{ number_format(abs($bersih), 0, ',', '.') }}</div>
                </div>
                @endif
            </div>
        </a>
    @empty
        <div class="list-group-item text-center text-muted py-4">Belum ada stok opname.</div>
    @endforelse
    </div>
</div>
<div class="mt-2">{{ $opnames->links() }}</div>
@endsection
