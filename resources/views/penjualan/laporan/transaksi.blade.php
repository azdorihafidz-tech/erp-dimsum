@extends('layouts.app')

@section('title', 'Laporan Transaksi')

@php
    $route = 'penjualan.laporan.transaksi';
    $isTransaksi = $posisi['level'] === 'transaksi';
    $namaKolom = match ($posisi['level']) { 'tahun' => 'Tahun', 'bulan' => 'Bulan', 'hari' => 'Tanggal', default => 'Jam' };
    $tingkatBawah = match ($posisi['level']) { 'tahun' => 'bulan', 'bulan' => 'hari', 'hari' => 'transaksi', default => 'struk' };
@endphp

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b">Laporan Transaksi</h4>
        <p class="text-muted mb-0" style="font-size:0.875rem">
            <i class="bi bi-shop me-1"></i>{{ $cabangNama }}
            @if($semuaCabang)<span class="text-muted">— ganti cabang di pojok kanan atas</span>@endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i><span class="d-none d-sm-inline">Export Excel</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i><span class="d-none d-sm-inline">Export PDF</span>
        </a>
        <x-panduan-button slug="laporan-transaksi-kasir" />
    </div>
</div>

{{-- Pilihan cepat + lompat ke tanggal --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group flex-wrap" role="group">
            @foreach([
                'hari_ini'  => ['Hari ini', ['tanggal' => today()->toDateString()]],
                'bulan_ini' => ['Bulan ini', ['tahun' => now()->year, 'bulan' => now()->month]],
                'tahun_ini' => ['Tahun ini', ['tahun' => now()->year]],
                'semua'     => ['Selama ini', ['semua' => 1]],
            ] as $key => [$label, $params])
            <a href="{{ route($route, $params) }}"
               class="btn btn-sm {{ $cepat === $key ? 'btn-dark' : 'btn-outline-dark' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route($route) }}" class="d-flex align-items-center gap-1 ms-sm-auto">
            <label class="small text-muted mb-0" for="lompatTanggal">Lihat tanggal</label>
            <input type="date" id="lompatTanggal" name="tanggal" class="form-control form-control-sm" style="width:auto"
                   value="{{ $isTransaksi ? $posisi['tanggal']->toDateString() : '' }}" max="{{ today()->toDateString() }}"
                   onchange="this.form.submit()">
        </form>
    </div>
</div>

{{-- Jejak halaman + tombol kembali --}}
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    @if($jejak['kembali'])
    <a href="{{ route($route, $jejak['kembali']) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
    @endif
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            @foreach($jejak['jejak'] as $j)
            @if($j['params'])
            <li class="breadcrumb-item"><a href="{{ route($route, $j['params']) }}">{{ $j['label'] }}</a></li>
            @else
            <li class="breadcrumb-item active fw-semibold" aria-current="page">{{ $j['label'] }}</li>
            @endif
            @endforeach
        </ol>
    </nav>
</div>

<h5 class="fw-bold mb-3">{{ $judul }}</h5>

{{-- Kotak total --}}
<div class="row g-3 mb-3">
    <div class="col-6 {{ $lihatUntung ? 'col-lg-4' : 'col-lg-6' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #1A1A1A !important">
            <div class="card-body py-3">
                <div class="text-muted small">Jumlah Transaksi</div>
                <div class="fs-4 fw-bold">{{ number_format($total['transaksi'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 {{ $lihatUntung ? 'col-lg-4' : 'col-lg-6' }}">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #FF6B00 !important">
            <div class="card-body py-3">
                <div class="text-muted small">Pendapatan <x-tooltip key="laporan_transaksi_kasir.pendapatan" /></div>
                <div class="fs-4 fw-bold" style="color:#FF6B00">{{ fmt_rupiah($total['pendapatan']) }}</div>
            </div>
        </div>
    </div>
    @if($lihatUntung)
    <div class="col-12 col-lg-4" id="kotakKeuntungan">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
            <div class="card-body py-3">
                <div class="text-muted small">Keuntungan <x-tooltip key="laporan_transaksi_kasir.keuntungan" /></div>
                <div class="fs-4 fw-bold {{ $total['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($total['keuntungan']) }}</div>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="row g-3">
    {{-- Grafik --}}
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart me-1"></i>Grafik {{ $isTransaksi ? 'per jam' : 'per ' . strtolower($namaKolom) }}
                @unless($isTransaksi)<x-tooltip key="laporan_transaksi_kasir.tingkat" />@endunless
            </div>
            <div class="card-body">
                @if($grafik->isEmpty())
                <div class="text-center text-muted py-5">Belum ada transaksi.</div>
                @else
                <div style="position:relative;height:320px"><canvas id="grafikTransaksi"></canvas></div>
                @unless($isTransaksi)
                <div class="text-muted small mt-2"><i class="bi bi-hand-index me-1"></i>Klik batang grafik atau baris tabel untuk melihat rincian per {{ $tingkatBawah }}.</div>
                @endunless
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel rincian --}}
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-ul me-1"></i>{{ $isTransaksi ? 'Daftar transaksi' : 'Rincian per ' . strtolower($namaKolom) }}
                @if($isTransaksi)<span class="text-muted small fw-normal">— klik untuk melihat struk</span>@endif
            </div>
            <div class="table-responsive" style="max-height:520px">
                <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.85rem">
                    <thead class="table-light" style="position:sticky;top:0;z-index:1">
                        @if($isTransaksi)
                        <tr>
                            <th>Jam</th>
                            <th>Transaksi</th>
                            <th class="text-end">Pendapatan</th>
                            @if($lihatUntung)<th class="text-end">Untung</th>@endif
                        </tr>
                        @else
                        <tr>
                            <th>{{ $namaKolom }}</th>
                            <th class="text-end">Trx</th>
                            <th class="text-end">Pendapatan</th>
                            @if($lihatUntung)<th class="text-end">Untung</th>@endif
                        </tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse($baris as $r)
                        @if($isTransaksi)
                        <tr role="button" onclick="openStrukModal({{ $r['id'] }})" title="Lihat struk">
                            <td class="text-muted">{{ $r['jam'] }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r['nomor'] }}</div>
                                <div class="text-muted" style="font-size:0.75rem">
                                    @if($semuaCabang && $r['cabang']){{ $r['cabang'] }} · @endif{{ $r['pelanggan'] }}
                                    @if($r['tipe']) · {{ $r['tipe'] }}@endif
                                    @if($r['metode']) · {{ $r['metode'] }}@endif
                                </div>
                            </td>
                            <td class="text-end">{{ fmt_rupiah($r['pendapatan']) }}</td>
                            @if($lihatUntung)
                            <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                            @endif
                        </tr>
                        @else
                        <tr role="button" class="{{ $r['transaksi'] ? '' : 'text-muted' }}"
                            onclick="window.location='{{ route($route, $r['params']) }}'">
                            <td>
                                <a href="{{ route($route, $r['params']) }}" class="text-decoration-none {{ $r['transaksi'] ? 'fw-semibold' : 'text-muted' }}">
                                    {{ $posisi['level'] === 'hari' ? \App\Services\LaporanTransaksiKasirService::tanggal(\Carbon\Carbon::parse($r['params']['tanggal']), 'pendek') : $r['label'] }}
                                </a>
                            </td>
                            <td class="text-end">{{ number_format($r['transaksi'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ fmt_rupiah($r['pendapatan']) }}</td>
                            @if($lihatUntung)
                            <td class="text-end {{ $r['keuntungan'] < 0 ? 'text-danger' : 'text-success' }}">{{ fmt_rupiah($r['keuntungan']) }}</td>
                            @endif
                        </tr>
                        @endif
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                        @endforelse
                    </tbody>
                    @if($baris->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="{{ $isTransaksi ? 2 : 1 }}">Total</td>
                            @unless($isTransaksi)<td class="text-end">{{ number_format($total['transaksi'], 0, ',', '.') }}</td>@endunless
                            <td class="text-end">{{ fmt_rupiah($total['pendapatan']) }}</td>
                            @if($lihatUntung)<td class="text-end">{{ fmt_rupiah($total['keuntungan']) }}</td>@endif
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>Pendapatan = uang yang dibayar pelanggan (setelah diskon, termasuk service charge &amp; take away fee).
    Transaksi yang dibatalkan, dihapus, atau masih bill tersimpan belum dibayar tidak dihitung.
</p>

@endsection

@if($grafik->isNotEmpty())
@php
    $dataGrafik = [
        'label'      => $grafik->pluck('label_pendek')->values(),
        'pendapatan' => $grafik->pluck('pendapatan')->values(),
        'transaksi'  => $grafik->map(fn ($g) => $g['transaksi'])->values(),
        'keuntungan' => $lihatUntung ? $grafik->pluck('keuntungan')->values() : null,
        'link'       => $isTransaksi ? null : $grafik->map(fn ($g) => route($route, $g['params']))->values(),
    ];
@endphp
@push('scripts')
<script>
(function () {
    const d = @json($dataGrafik);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const datasets = [{
        type: 'bar', label: 'Pendapatan', data: d.pendapatan, backgroundColor: '#FF6B00', borderRadius: 4, yAxisID: 'y', order: 2,
    }];
    @if($lihatUntung)
    datasets.push({ type: 'bar', label: 'Keuntungan', data: d.keuntungan, backgroundColor: '#198754', borderRadius: 4, yAxisID: 'y', order: 3 });
    @endif
    datasets.push({
        type: 'line', label: 'Jumlah transaksi', data: d.transaksi, borderColor: '#1A1A1A', backgroundColor: '#1A1A1A',
        tension: 0.3, pointRadius: 3, yAxisID: 'y1', order: 1,
    });

    new Chart(document.getElementById('grafikTransaksi'), {
        data: { labels: d.label, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'y1'
                    ? ' ' + c.dataset.label + ': ' + c.raw
                    : ' ' + c.dataset.label + ': ' + rp(c.raw) } },
                legend: { position: 'bottom' },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => v >= 1e6 ? (v / 1e6).toLocaleString('id-ID') + 'jt' : (v >= 1e3 ? (v / 1e3).toLocaleString('id-ID') + 'rb' : v) } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
            },
            onClick: (evt, el, chart) => {
                if (!d.link) return;
                const p = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true);
                if (p.length) window.location = d.link[p[0].index];
            },
            onHover: (evt, el) => { if (d.link) evt.native.target.style.cursor = el.length ? 'pointer' : 'default'; },
        },
    });
})();
</script>
@endpush
@endif
