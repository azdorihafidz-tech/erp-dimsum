@extends('layouts.app')

@section('title', 'Ganti Rugi Stok')

@php $Rp = fn ($n) => 'Rp ' . number_format(abs((float) $n), 0, ',', '.'); @endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-exclamation me-2 text-warning"></i>Ganti Rugi Stok (Karyawan)</h5>
    <x-panduan-button slug="ganti-rugi-stok" />
</div>

<div class="alert alert-info small py-2">
    Ganti rugi dibebankan dari halaman <strong>Stok Opname</strong> yang sudah disetujui (barang Hilang). Potong gaji otomatis masuk slip <strong>Penggajian</strong> mulai bulan berikutnya.
    Sisa tagihan berjalan: <strong class="text-danger">{{ $Rp($sisa) }}</strong>
</div>

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        @if($lokasi->count() > 1)
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Lokasi</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua lokasi</option>
                @foreach($lokasi as $l)<option value="{{ $l->id }}" @selected($filter == $l->id)>{{ $l->nama_cabang }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                @foreach(['berjalan' => 'Berjalan', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan', 'semua' => 'Semua'] as $v => $l)
                <option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </div>
</form>

<div class="card"><div class="list-group list-group-flush">
@forelse($rows as $g)
    <div class="list-group-item py-2">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">{{ $g->karyawan?->nama_lengkap }}
                    <span class="badge bg-{{ ['berjalan' => 'warning', 'lunas' => 'success', 'batal' => 'secondary'][$g->status] ?? 'light' }}">{{ ucfirst($g->status) }}</span></div>
                <div class="small text-muted">{{ $g->cabang?->nama_cabang }} · <a href="{{ route('stok-opname.show', $g->stok_opname_id) }}">{{ $g->opname?->nomor }}</a> · {{ $g->labelCara() }}@if($g->kas) ke {{ $g->kas->nama_kas }}@endif</div>
                @if($g->alasan_nilai)<div class="small">Alasan nilai: {{ $g->alasan_nilai }}</div>@endif
                @if($g->alasan_batal)<div class="small">Dibatalkan: {{ $g->alasan_batal }}</div>@endif
                @foreach($g->potongan as $p)
                <div class="small text-muted"><i class="bi bi-arrow-return-right"></i> Slip {{ $p->periode }}: {{ $Rp($p->nominal) }} — {{ $p->status === 'dibayar' ? 'sudah dibayar' : 'di slip (belum dibayar)' }}</div>
                @endforeach
            </div>
            <div class="text-end small">
                <div>Nilai <strong>{{ $Rp($g->nilai) }}</strong></div>
                <div class="text-success">Diterima {{ $Rp($g->terbayar) }}</div>
                <div class="text-danger">Sisa {{ $Rp($g->sisa()) }}</div>
                @if($g->status === 'berjalan' && (float) $g->terbayar <= 0)
                <form method="POST" action="{{ route('ganti-rugi.batal', $g) }}" class="mt-1 d-flex gap-1" onsubmit="return confirm('Batalkan ganti rugi ini?')">
                    @csrf
                    <input type="text" name="alasan" class="form-control form-control-sm" placeholder="Alasan batal" required maxlength="255">
                    <button class="btn btn-sm btn-outline-secondary">Batal</button>
                </form>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="list-group-item text-center text-muted py-4">Belum ada ganti rugi.</div>
@endforelse
</div></div>
<div class="mt-2">{{ $rows->links() }}</div>
@endsection
