@extends('layouts.app')

@section('title', 'Dashboard Stok Opname')

@php
    $Rp = fn ($n) => ((float) $n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $warnaStatus = ['sudah' => ['success', 'Sudah', 'bi-check-circle'], 'menunggu' => ['warning', 'Menunggu persetujuan', 'bi-hourglass-split'],
        'menghitung' => ['info', 'Sedang dihitung', 'bi-pencil-square'], 'belum' => ['danger', 'Belum', 'bi-exclamation-octagon']];
    $belum = $d['status']->where('kode', 'belum')->count();
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Stok Opname — {{ \App\Services\StokOpnameDashboardService::BULAN_PANJANG[now()->month - 1] }} {{ now()->year }}</h5>
    <div class="d-flex gap-2 flex-wrap">
        <x-panduan-button slug="stok-opname" />
        <a href="{{ route('stok-opname.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i>Daftar Opname</a>
        @if(auth()->user()->hasPermission('stok_opname.ajukan'))
        <a href="{{ route('stok-opname.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Mulai Opname</a>
        @endif
    </div>
</div>

@if($d['pengingat'] && $belum > 0)
<div class="alert alert-danger py-2"><i class="bi bi-bell me-1"></i>Masa pengingat akhir bulan: <strong>{{ $belum }} lokasi</strong> belum melakukan stok opname bulan ini.</div>
@endif

<div class="card mb-3">
    <div class="card-header py-2 fw-semibold">Status bulan ini per lokasi</div>
    <div class="list-group list-group-flush">
    @foreach($d['status'] as $s)
        @php [$w, $l, $i] = $warnaStatus[$s->kode]; @endphp
        <div class="list-group-item d-flex justify-content-between align-items-center gap-2 py-2 {{ $s->kode === 'belum' ? 'list-group-item-danger' : '' }}">
            <span>{{ $s->cabang->nama_cabang }}</span>
            <span class="text-end">
                <span class="badge bg-{{ $w }}"><i class="bi {{ $i }} me-1"></i>{{ $l }}</span>
                @if($s->opname)<a href="{{ route('stok-opname.show', $s->opname) }}" class="small ms-1">{{ $s->opname->nomor }}</a>
                    @if($s->kode === 'sudah' && $s->opname->akurasi !== null)<span class="small text-muted ms-1">akurasi {{ $A($s->opname->akurasi) }}%</span>@endif
                @endif
            </span>
        </div>
    @endforeach
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Menunggu persetujuan</div>
        <div class="list-group list-group-flush">
        @forelse($d['menunggu'] as $o)
            <a href="{{ route('stok-opname.show', $o) }}" class="list-group-item list-group-item-action py-2 small">
                <strong>{{ $o->nomor }}</strong> · {{ $o->cabang?->nama_cabang }} · diajukan {{ $o->diajukan_at?->format('d/m H:i') }} oleh {{ $o->pengaju?->name }}
            </a>
        @empty
            <div class="list-group-item text-muted small py-3 text-center">Tidak ada.</div>
        @endforelse
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">Kerugian per penyebab — bulan ini vs bulan lalu</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Penyebab</th><th class="text-end">Bulan ini</th><th class="text-end">Bulan lalu</th></tr></thead>
            <tbody>
            @forelse($d['penyebab'] as $p)
            <tr><td>{{ $p->label }}</td><td class="text-end">{{ $p->lebih ? '+' : '' }}{{ $Rp($p->ini) }}</td><td class="text-end text-muted">{{ $p->lebih ? '+' : '' }}{{ $Rp($p->lalu) }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada kerugian tercatat.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr class="fw-semibold"><td>Kerugian bersih</td><td class="text-end">{{ $Rp($d['kerugian_ini']['kerugian_bersih']) }}</td><td class="text-end text-muted">{{ $Rp($d['kerugian_lalu']['kerugian_bersih']) }}</td></tr></tfoot>
        </table></div></div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-header py-2 fw-semibold">Akurasi 6 bulan per lokasi (%) <x-tooltip key="stok_opname.akurasi" /></div>
        <div class="card-body">
            @if(empty($d['akurasi']['seri']))
            <div class="text-center text-muted small py-3">Belum ada opname yang disetujui dalam 6 bulan terakhir.</div>
            @else
            <div style="position:relative;height:260px"><canvas id="grafikAkurasi" aria-label="Grafik akurasi stok opname per lokasi" role="img"></canvas></div>
            <div class="table-responsive mt-2"><table class="table table-sm small mb-0" id="tabelAkurasi">
                <thead><tr><th>Lokasi</th>@foreach($d['akurasi']['label'] as $b)<th class="text-end">{{ $b }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($d['akurasi']['seri'] as $s)
                <tr><td>{{ $s['nama'] }}</td>@foreach($s['data'] as $v)<td class="text-end">{{ $v === null ? '—' : $A($v) . '%' }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table></div>
            @endif
        </div></div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-header py-2 fw-semibold">10 barang paling sering selisih (6 bulan)</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle small">
            <thead><tr><th>Barang</th><th>Lokasi</th><th class="text-end">Kali</th><th class="text-end">Rugi</th></tr></thead>
            <tbody>
            @forelse($d['sering_selisih'] as $b)
            <tr><td>{{ $b->nama_item }}</td><td>{{ $b->nama_cabang }}</td><td class="text-end">{{ $b->kali }}</td><td class="text-end">{{ $Rp($b->rugi) }}</td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card mb-3"><div class="card-header py-2 fw-semibold d-flex justify-content-between">Stok rusak tertahan
            @canany(['stok_opname.stok_rusak', 'stok_opname.view'])<a href="{{ route('stok-rusak.index') }}" class="small fw-normal">Buka</a>@endcanany</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small">
            <thead><tr><th>Lokasi</th><th class="text-end">Baris</th><th class="text-end">Nilai</th><th class="text-end">Umur tertua</th></tr></thead>
            <tbody>
            @forelse($d['stok_rusak'] as $s)
            <tr><td>{{ $s->nama_cabang }}</td><td class="text-end">{{ $s->baris }}</td><td class="text-end">{{ $Rp($s->nilai) }}</td><td class="text-end">{{ $s->umur }} hari</td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
        @if($d['ganti_rugi'])
        <div class="card"><div class="card-header py-2 fw-semibold">Ganti rugi karyawan</div>
            <div class="card-body small py-2">
                Dibebankan bulan ini {{ $Rp($d['ganti_rugi']->dibebankan) }} · diterima {{ $Rp($d['ganti_rugi']->diterima) }} ·
                sisa tagihan berjalan <strong class="text-danger">{{ $Rp($d['ganti_rugi']->sisa) }}</strong>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if(! empty($d['akurasi']['seri']))
<script>
(function () {
    // Palet kategori tervalidasi (dataviz), urutan tetap per lokasi; mode gelap memakai langkah gelapnya.
    const gelap = document.body.classList.contains('dark-mode');
    const WARNA = gelap
        ? ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#0ca30c', '#9085e9', '#e66767']
        : ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
    const SERI = @json($d['akurasi']['seri']);
    const teks = gelap ? '#c3c2b7' : '#475569';
    const grid = gelap ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.06)';
    new Chart(document.getElementById('grafikAkurasi'), {
        type: 'line',
        data: {
            labels: @json($d['akurasi']['label']),
            datasets: SERI.slice(0, 8).map((s, i) => ({
                label: s.nama, data: s.data, borderColor: WARNA[i], backgroundColor: WARNA[i],
                borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, spanGaps: true, tension: 0,
            })),
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { min: 0, max: 100, ticks: { color: teks, callback: v => v + '%' }, grid: { color: grid } },
                x: { ticks: { color: teks }, grid: { display: false } },
            },
            plugins: {
                legend: { labels: { color: teks, usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: c => `${c.dataset.label}: ${c.raw === null ? '—' : c.raw.toLocaleString('id-ID') + '%'}` } },
            },
        },
    });
})();
</script>
@endif
@endpush
