{{--
    Banner daftar Bahan Baku / Produk Jual (CLAUDE.md 4.38-F): harga di daftar
    SELALU milik lokasi yang disebut di sini, supaya tidak rancu dgn POS outlet.
    Props: $outlet (Cabang|null = harga umum), $pilihan (Collection<Cabang>),
    $bolehUmum (bool — owner/admin pusat boleh lihat harga umum).
--}}
@props(['outlet' => null, 'pilihan', 'bolehUmum' => false])
<form method="GET" class="alert {{ $outlet ? 'alert-warning' : 'alert-secondary' }} d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
    @foreach(request()->except(['cabang_id', 'page']) as $k => $v)
        @if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <i class="bi bi-geo-alt-fill"></i>
    @if($outlet)
        <span>Harga yang tampil adalah harga di <strong>{{ $outlet->nama_cabang }}</strong></span>
    @else
        <span>Harga yang tampil adalah <strong>Harga Umum</strong> (dipakai outlet yang tidak punya harga sendiri)</span>
    @endif
    @if($pilihan->count() > 1 || $bolehUmum)
        <span class="ms-auto small text-muted">Lihat harga di:</span>
        <select name="cabang_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @if($bolehUmum)<option value="0" @selected(! $outlet)>Harga Umum</option>@endif
            @foreach($pilihan as $c)
                <option value="{{ $c->id }}" @selected($outlet?->id === $c->id)>{{ $c->nama_cabang }}</option>
            @endforeach
        </select>
    @endif
</form>
