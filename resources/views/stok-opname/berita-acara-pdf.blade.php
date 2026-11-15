@extends('laporan.pdf.layout')

@php
    $o = $d['o'];
    $rk = $d['ringkasan'];
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $Rp = fn ($n) => ((float) $n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
@endphp

@push('pdf-styles')
<style>
    .sementara { position: fixed; top: 38%; left: 8%; font-size: 78px; color: rgba(220, 38, 38, 0.12); transform: rotate(-30deg); font-weight: bold; }
    .info td { padding: 2px 6px; font-size: 10px; }
    .ttd { width: 100%; margin-top: 24px; border-collapse: collapse; }
    .ttd td { width: 33%; text-align: center; font-size: 10px; vertical-align: top; padding-top: 4px; }
    .ttd .garis { margin-top: 52px; border-top: 1px solid #334155; display: inline-block; width: 75%; padding-top: 3px; }
    .foto { width: 31%; display: inline-block; vertical-align: top; margin: 0 1% 10px 0; font-size: 9px; }
    .foto img { width: 100%; max-height: 150px; object-fit: cover; border: 1px solid #e2e8f0; }
    td.minus { color: #b91c1c; }
    td.plus { color: #15803d; }
</style>
@endpush

@section('content')
@unless($d['final'])
<div class="sementara">SEMENTARA</div>
@endunless

<table class="info layout" style="margin-bottom:10px">
    <tr><td style="width:18%">Nomor</td><td style="width:32%"><strong>{{ $o->nomor }}</strong></td><td style="width:18%">Lokasi</td><td>{{ $o->cabang?->nama_cabang }}</td></tr>
    <tr><td>Cakupan</td><td>{{ $o->labelCakupan() }}</td><td>Status</td><td>{{ $o->labelStatus() }}</td></tr>
    <tr><td>Dimulai</td><td>{{ $o->created_at->format('d/m/Y H:i') }}</td><td>Disetujui</td><td>{{ $o->disetujui_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td>Penghitung</td><td colspan="3">{{ implode(', ', $d['penghitung']) ?: '—' }}</td></tr>
</table>

<table class="data">
    <thead><tr>
        <th>Barang</th><th>Satuan</th><th style="text-align:right">Sistem</th><th style="text-align:right">Fisik</th>
        <th style="text-align:right">Selisih</th><th style="text-align:right">Harga Pokok</th><th style="text-align:right">Nilai</th><th>Penyebab</th>
    </tr></thead>
    <tbody>
    @forelse($d['baris'] as $b)
    <tr class="{{ ($b['selisih'] ?? 0) < -0.0005 ? 'highlight-danger' : '' }}">
        <td>{{ $b['nama'] }}<br><span style="color:#94a3b8">{{ $b['kode'] }}</span></td>
        <td>{{ $b['satuan'] }}</td>
        <td class="num">{{ $A($b['sistem']) }}</td>
        <td class="num">{{ $A($b['fisik']) }}</td>
        <td class="num {{ ($b['selisih'] ?? 0) < 0 ? 'minus' : (($b['selisih'] ?? 0) > 0 ? 'plus' : '') }}">{{ ($b['selisih'] ?? 0) > 0 ? '+' : '' }}{{ $A($b['selisih']) }}</td>
        <td class="num">{{ $Rp($b['harga']) }}</td>
        <td class="num">{{ abs($b['nilai']) > 0 ? $Rp($b['nilai']) : '—' }}</td>
        <td>{{ $b['penyebab'] }}</td>
    </tr>
    @empty
    <tr><td colspan="8" class="empty-note">Tidak ada barang.</td></tr>
    @endforelse
    </tbody>
</table>

@if($d['dikeluarkan'])
<p style="font-size:10px;margin-bottom:4px"><strong>Tidak dihitung (dikeluarkan dari opname):</strong></p>
<table class="data"><tbody>
@foreach($d['dikeluarkan'] as $k)
<tr><td>{{ $k['nama'] }} ({{ $k['kode'] }})</td><td>{{ $k['alasan'] }}</td></tr>
@endforeach
</tbody></table>
@endif

<table class="data" style="width:60%">
    <thead><tr><th colspan="2">Ringkasan</th></tr></thead>
    <tbody>
        <tr><td>Barang dihitung / cocok / kurang / lebih</td><td class="num">{{ $rk['dihitung'] }} / {{ $rk['cocok'] }} / {{ $rk['kurang'] }} / {{ $rk['lebih'] }}</td></tr>
        <tr><td>Nilai kurang</td><td class="num minus">{{ $Rp(-$rk['nilai_kurang']) }}</td></tr>
        <tr><td>Nilai lebih</td><td class="num plus">{{ $Rp($rk['nilai_lebih']) }}</td></tr>
        <tr class="subtotal"><td>Nilai bersih</td><td class="num">{{ $Rp($rk['nilai_bersih']) }}</td></tr>
        <tr><td>Akurasi (barang cocok ÷ dihitung)</td><td class="num"><strong>{{ $rk['akurasi'] !== null ? $A($rk['akurasi']) . '%' : '—' }}</strong></td></tr>
        <tr><td>Akurasi nilai</td><td class="num">{{ $rk['akurasi_nilai'] !== null ? $A($rk['akurasi_nilai']) . '%' : '—' }}</td></tr>
    </tbody>
</table>
@unless($d['final'])
<p style="font-size:9px;color:#b91c1c">Dokumen SEMENTARA: nilai masih perkiraan (harga pokok FIFO saat ini). Stok & keuangan belum berubah sampai opname disetujui.</p>
@endunless

<table class="ttd">
    <tr>
        <td>Penghitung<br><span class="garis">{{ implode(', ', array_slice($d['penghitung'], 0, 2)) ?: '(..................)' }}</span></td>
        <td>Pemeriksa<br><span class="garis">{{ $o->pengaju?->name ?? '(..................)' }}</span></td>
        <td>Penyetuju<br><span class="garis">{{ $o->penyetuju?->name ?? '(..................)' }}</span></td>
    </tr>
</table>

@if($d['foto'])
<div style="page-break-before: always"></div>
<p style="font-size:11px;font-weight:bold;margin-bottom:8px">Lampiran Foto</p>
@foreach($d['foto'] as $f)
@php $path = \Illuminate\Support\Facades\Storage::disk('public')->path($f['path']); @endphp
<div class="foto">
    @if(is_file($path))
    <img src="data:{{ mime_content_type($path) ?: 'image/jpeg' }};base64,{{ base64_encode(file_get_contents($path)) }}">
    @else
    <div class="empty-note">(file foto tidak ditemukan)</div>
    @endif
    <div>{{ $f['nama'] }} — {{ $f['penyebab'] }} {{ $A($f['qty']) }}{{ $f['catatan'] ? ' · ' . $f['catatan'] : '' }}</div>
</div>
@endforeach
@endif
@endsection
