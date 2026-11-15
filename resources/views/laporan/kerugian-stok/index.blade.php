@extends('layouts.app')

@section('title', 'Laporan Kerugian Stok')

@php
    $Rp = fn ($n) => ((float) $n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $q = request()->only(['cabang_id', 'dari', 'sampai']);
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-down-arrow me-2 text-danger"></i>Laporan Kerugian Stok</h5>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <x-panduan-button slug="laporan-kerugian-stok" />
        <a href="{{ route('laporan.kerugian-stok', array_merge($q, ['format' => 'excel'])) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a href="{{ route('laporan.kerugian-stok', array_merge($q, ['format' => 'pdf'])) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    </div>
</div>

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        @if($lokasi->count() > 1)
        <div class="col-12 col-md-3">
            <label class="form-label small mb-0">Lokasi</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua lokasi</option>
                @foreach($lokasi as $l)
                <option value="{{ $l->id }}" @selected($filter == $l->id)>{{ $l->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Dari</label><input type="date" name="dari" value="{{ $dari->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Sampai</label><input type="date" name="sampai" value="{{ $sampai->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </div>
</form>

<div class="row g-2 mb-3">
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Kerugian kotor <x-tooltip key="laporan_kerugian_stok.kotor" /></div><div class="fs-5 fw-bold text-danger">{{ $Rp($data['kerugian_kotor']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Dipulihkan</div><div class="fs-5 fw-bold text-success">{{ $Rp($data['dipulihkan']) }}</div><div class="small text-muted">uang {{ $Rp($data['dipulihkan_uang']) }} · barang kembali {{ $Rp($data['dibatalkan']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Kelebihan stok</div><div class="fs-5 fw-bold">{{ $Rp($data['nilai_lebih']) }}</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Kerugian bersih <x-tooltip key="laporan_kerugian_stok.bersih" /></div><div class="fs-5 fw-bold {{ $data['kerugian_bersih'] > 0 ? 'text-danger' : 'text-success' }}">{{ $Rp($data['kerugian_bersih']) }}</div></div></div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Per penyebab</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Penyebab</th><th class="text-end">Barang</th><th class="text-end">Nilai</th></tr></thead>
            <tbody>
            @forelse($data['per_penyebab'] as $p)
            <tr><td>{{ $p->label }}</td><td class="text-end">{{ $p->barang }}</td><td class="text-end {{ $p->penyebab === 'lebih' ? 'text-success' : '' }}">{{ $p->penyebab === 'lebih' ? '+' : '' }}{{ $Rp($p->nilai) }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada opname disetujui di periode ini.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Per lokasi</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Lokasi</th><th class="text-end">Opname</th><th class="text-end">Akurasi</th><th class="text-end">Kurang</th><th class="text-end">Lebih</th></tr></thead>
            <tbody>
            @forelse($data['per_lokasi'] as $l)
            <tr><td>{{ $l->nama }}</td><td class="text-end">{{ $l->opname }}</td><td class="text-end">{{ $l->akurasi !== null ? $A($l->akurasi) . '%' : '—' }}</td><td class="text-end text-danger">{{ $Rp($l->kurang) }}</td><td class="text-end">{{ $Rp($l->lebih) }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-header py-2 fw-semibold">Per barang (kerugian terbesar)</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Barang</th><th>Lokasi</th><th class="text-end">Jumlah</th><th class="text-end">Nilai</th><th>Penyebab</th><th class="text-end">Kali</th></tr></thead>
            <tbody>
            @forelse($data['per_barang'] as $b)
            <tr><td>{{ $b->nama_item }} <span class="small text-muted">{{ $b->kode_item }}</span></td><td>{{ $b->nama_cabang }}</td><td class="text-end">{{ $A($b->qty) }} {{ $b->satuan }}</td><td class="text-end text-danger">{{ $Rp($b->nilai) }}</td><td class="small">{{ $b->penyebab }}</td><td class="text-end">{{ $b->opname }}</td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-3">—</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Stok rusak tertahan (saat ini)</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Lokasi</th><th class="text-end">Baris</th><th class="text-end">Nilai</th><th class="text-end">Umur tertua</th></tr></thead>
            <tbody>
            @forelse($data['stok_rusak'] as $s)
            <tr><td>{{ $s->nama_cabang }}</td><td class="text-end">{{ $s->baris }}</td><td class="text-end">{{ $Rp($s->nilai) }}</td><td class="text-end">{{ $s->umur }} hari</td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada stok rusak tertahan.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($data['tindak_lanjut']->isNotEmpty())
        <div class="card-body py-2 small border-top">
            <strong>Tindak lanjut periode ini:</strong>
            @foreach($data['tindak_lanjut'] as $t)
            <div>{{ \App\Services\StokRusakService::LABEL_TINDAKAN[$t->jenis] ?? $t->jenis }}: {{ $t->kali }}× · nilai barang {{ $Rp($t->nilai) }}@if((float) $t->uang > 0) · uang {{ $Rp($t->uang) }}@endif</div>
            @endforeach
        </div>
        @endif
        </div>
    </div>
    @if($data['ganti_rugi'])
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Ganti rugi karyawan</div>
        <div class="card-body small">
            <div>Dibebankan periode ini: <strong>{{ $Rp($data['ganti_rugi']->dibebankan) }}</strong></div>
            <div>Sudah diterima: <strong class="text-success">{{ $Rp($data['ganti_rugi']->diterima) }}</strong></div>
            <div>Sisa tagihan berjalan (semua periode): <strong class="text-danger">{{ $Rp($data['ganti_rugi']->sisa) }}</strong></div>
        </div></div>
    </div>
    @endif
</div>
<p class="small text-muted mt-3 mb-0">Sumber: opname yang <strong>disetujui</strong> pada periode ini (tanggal persetujuan). Kerugian dicatat sebagai beban tanpa kas dan juga tampil per akun di Laba Rugi Formal.</p>
@endsection
