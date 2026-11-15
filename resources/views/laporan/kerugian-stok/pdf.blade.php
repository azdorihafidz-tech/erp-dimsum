@extends('laporan.pdf.layout')

@php
    $Rp = fn ($n) => ((float) $n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
@endphp

@section('content')
<table class="data" style="width:60%">
    <tbody>
        <tr><td>Kerugian kotor</td><td class="num">{{ $Rp($data['kerugian_kotor']) }}</td></tr>
        <tr><td>Dipulihkan (uang)</td><td class="num">{{ $Rp($data['dipulihkan_uang']) }}</td></tr>
        <tr><td>Kerugian dibatalkan (barang kembali)</td><td class="num">{{ $Rp($data['dibatalkan']) }}</td></tr>
        <tr><td>Kelebihan stok</td><td class="num">{{ $Rp($data['nilai_lebih']) }}</td></tr>
        <tr class="grand"><td>Kerugian bersih</td><td class="num">{{ $Rp($data['kerugian_bersih']) }}</td></tr>
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Penyebab</th><th style="text-align:right">Barang</th><th style="text-align:right">Kejadian</th><th style="text-align:right">Nilai</th></tr></thead>
    <tbody>
    @forelse($data['per_penyebab'] as $p)
    <tr><td>{{ $p->label }}</td><td class="num">{{ $p->barang }}</td><td class="num">{{ $p->kejadian }}</td><td class="num">{{ $Rp($p->nilai) }}</td></tr>
    @empty
    <tr><td colspan="4" class="empty-note">Belum ada opname disetujui di periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Lokasi</th><th style="text-align:right">Opname</th><th style="text-align:right">Akurasi</th><th style="text-align:right">Kurang</th><th style="text-align:right">Lebih</th></tr></thead>
    <tbody>
    @foreach($data['per_lokasi'] as $l)
    <tr><td>{{ $l->nama }}</td><td class="num">{{ $l->opname }}</td><td class="num">{{ $l->akurasi !== null ? $A($l->akurasi) . '%' : '—' }}</td><td class="num">{{ $Rp($l->kurang) }}</td><td class="num">{{ $Rp($l->lebih) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table class="data">
    <thead><tr><th>Barang</th><th>Lokasi</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Nilai</th><th>Penyebab</th></tr></thead>
    <tbody>
    @foreach($data['per_barang'] as $b)
    <tr><td>{{ $b->nama_item }}</td><td>{{ $b->nama_cabang }}</td><td class="num">{{ $A($b->qty) }} {{ $b->satuan }}</td><td class="num">{{ $Rp($b->nilai) }}</td><td>{{ $b->penyebab }}</td></tr>
    @endforeach
    </tbody>
</table>

@if($data['stok_rusak']->isNotEmpty())
<table class="data" style="width:70%">
    <thead><tr><th>Stok rusak tertahan</th><th style="text-align:right">Baris</th><th style="text-align:right">Nilai</th><th style="text-align:right">Umur tertua</th></tr></thead>
    <tbody>
    @foreach($data['stok_rusak'] as $s)
    <tr><td>{{ $s->nama_cabang }}</td><td class="num">{{ $s->baris }}</td><td class="num">{{ $Rp($s->nilai) }}</td><td class="num">{{ $s->umur }} hari</td></tr>
    @endforeach
    </tbody>
</table>
@endif
@if($data['ganti_rugi'])
<p style="font-size:10px">Ganti rugi karyawan — dibebankan {{ $Rp($data['ganti_rugi']->dibebankan) }}, diterima {{ $Rp($data['ganti_rugi']->diterima) }}, sisa tagihan {{ $Rp($data['ganti_rugi']->sisa) }}.</p>
@endif
@endsection
