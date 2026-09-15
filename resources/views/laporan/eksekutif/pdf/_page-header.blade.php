@php
    /** @var string $judulHalaman */
@endphp
<table class="layout header-table">
    <tr>
        <td style="width: 55px;">
            @if(file_exists(public_path('images/logo.png')))
            {{-- PDF (DomPDF) butuh path filesystem lokal; preview HTML browser
                 butuh URL web biasa — path filesystem mentah tidak bisa
                 dimuat browser sebagai <img src>. $isPdf dibedakan di
                 controller (export() set true, generate()/preview tidak). --}}
            <img src="{{ ($isPdf ?? false) ? public_path('images/logo.png') : asset('images/logo.png') }}" class="logo">
            @endif
        </td>
        <td>
            <div class="company-name">D'mentai</div>
            <div class="company-sub">Dimsum &amp; Gyoza</div>
        </td>
        <td style="text-align:right">
            <div class="report-title">LAPORAN EKSEKUTIF KEUANGAN</div>
            <div class="report-meta">
                Per {{ $tanggal->translatedFormat('d F Y') }} — {{ $cabangNama }}<br>
                Dicetak: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>
<div class="divider"></div>
<div class="page-heading">{{ $judulHalaman }}</div>
