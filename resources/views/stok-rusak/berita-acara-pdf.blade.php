@extends('laporan.pdf.layout')

@php
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $Rp = fn ($n) => 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $o = $s->rincian?->opnameItem?->opname;
    $path = $t->foto_path ? \Illuminate\Support\Facades\Storage::disk('public')->path($t->foto_path) : null;
@endphp

@push('pdf-styles')
<style>
    .isi { font-size: 11px; line-height: 1.6; }
    .ttd { width: 100%; margin-top: 30px; border-collapse: collapse; }
    .ttd td { width: 50%; text-align: center; font-size: 10px; vertical-align: top; }
    .ttd .garis { margin-top: 56px; border-top: 1px solid #334155; display: inline-block; width: 70%; padding-top: 3px; }
</style>
@endpush

@section('content')
<p class="isi">Pada tanggal <strong>{{ $t->tanggal->format('d/m/Y') }}</strong> di <strong>{{ $s->cabang?->nama_cabang }}</strong> telah dimusnahkan barang berikut:</p>

<table class="data" style="margin-top:8px">
    <thead><tr><th>Barang</th><th style="text-align:right">Jumlah</th><th>Penyebab</th><th style="text-align:right">Harga Pokok</th><th style="text-align:right">Nilai</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ $s->item?->nama_item }}<br><span style="color:#94a3b8">{{ $s->item?->kode_item }}</span></td>
            <td class="num">{{ $A($t->qty) }} {{ $s->item?->satuan }}</td>
            <td>{{ $s->penyebab->labelPendek() }}</td>
            <td class="num">{{ $Rp($s->harga_pokok) }}</td>
            <td class="num">{{ $Rp($t->nilai) }}</td>
        </tr>
    </tbody>
</table>

<table class="data" style="width:70%">
    <tbody>
        <tr><td style="width:35%">Nomor berita acara</td><td><strong>{{ $t->nomor_ba }}</strong></td></tr>
        <tr><td>Asal</td><td>{{ $o ? 'Stok Opname ' . $o->nomor . ' (disetujui ' . $o->disetujui_at?->format('d/m/Y') . ')' : '—' }}</td></tr>
        <tr><td>Dicatat oleh</td><td>{{ $t->user?->name }}</td></tr>
        <tr><td>Saksi</td><td>{{ $t->saksi ?: '—' }}</td></tr>
        <tr><td>Catatan</td><td>{{ $t->catatan ?: '—' }}</td></tr>
    </tbody>
</table>
<p class="isi">Kerugian atas barang ini sudah dicatat saat Stok Opname disetujui. Pemusnahan ini tidak menimbulkan catatan keuangan baru.</p>

@if($path && is_file($path))
<p style="font-size:10px;font-weight:bold;margin:8px 0 4px">Foto pemusnahan</p>
<img src="data:{{ mime_content_type($path) ?: 'image/jpeg' }};base64,{{ base64_encode(file_get_contents($path)) }}" style="max-width:60%;max-height:260px;border:1px solid #e2e8f0">
@endif

<table class="ttd">
    <tr>
        <td>Yang memusnahkan<br><span class="garis">{{ $t->user?->name }}</span></td>
        <td>Saksi<br><span class="garis">{{ $t->saksi ?: '(..................)' }}</span></td>
    </tr>
</table>
@endsection
