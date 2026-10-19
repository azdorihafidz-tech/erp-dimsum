{{--
    Penanda "lokasi yang sedang dilihat" + pilihan outlet (CLAUDE.md 4.38-F).
    Dipakai di halaman Harga & Stok per Outlet, supaya user selalu tahu harga
    yang tampil milik outlet mana.
    Props: $outlet (Cabang), $pilihan (Collection<Cabang>), $action (URL GET).
--}}
@props(['outlet', 'pilihan', 'action'])
<form method="GET" action="{{ $action }}" class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-3">
    <i class="bi bi-geo-alt-fill"></i>
    <span>Anda sedang mengatur untuk: <strong>{{ $outlet->nama_cabang }}</strong></span>
    @if($pilihan->count() > 1)
        <span class="ms-auto small text-muted">Ganti outlet:</span>
        <select name="cabang_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @foreach($pilihan as $c)
                <option value="{{ $c->id }}" @selected($c->id === $outlet->id)>{{ $c->nama_cabang }}</option>
            @endforeach
        </select>
    @endif
</form>
