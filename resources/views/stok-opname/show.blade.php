@extends('layouts.app')

@section('title', 'Stok Opname ' . $o->nomor)

@php
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $Rp = fn ($n) => 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $editable = $o->status === 'menghitung';
    $aktif = $rows->where('dikeluarkan', false);
    $sudah = $aktif->filter(fn ($r) => $r->sudahDihitung())->count();
    $penyebabJs = collect($penyebabKurang)->map(fn ($p) => ['v' => $p->value, 'l' => $p->label()])->values();
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2 text-primary"></i>{{ $o->nomor }}
            <span class="badge bg-{{ $o->warnaStatus() }} align-middle">{{ $o->labelStatus() }}</span></h5>
        <div class="small text-muted">{{ $o->cabang?->nama_cabang }} · {{ $o->labelCakupan() }} · dimulai {{ $o->created_at->format('d/m/Y H:i') }} oleh {{ $o->pembuat?->name ?? '-' }}</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <x-panduan-button slug="stok-opname" />
        <a href="{{ route('stok-opname.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Daftar</a>
    </div>
</div>

@if($o->ditolak())
<div class="alert alert-danger py-2"><i class="bi bi-x-octagon me-1"></i><strong>Ditolak</strong> oleh {{ $o->penolak?->name }} ({{ $o->ditolak_at?->format('d/m/Y H:i') }}): {{ $o->alasan_tolak }}</div>
@endif
@if($o->status === 'dibatalkan')
<div class="alert alert-secondary py-2">Dibatalkan oleh {{ $o->pembatal?->name }} ({{ $o->dibatalkan_at?->format('d/m/Y H:i') }}): {{ $o->alasan_batal }}</div>
@endif
@if($o->status === 'disetujui')
@php $rk = $o->ringkasan ?? []; @endphp
<div class="alert alert-success py-2">
    <i class="bi bi-check-circle me-1"></i>Disetujui oleh {{ $o->penyetuju?->name }} ({{ $o->disetujui_at?->format('d/m/Y H:i') }}). Stok & keuangan sudah disesuaikan.
    <div class="small mt-1">
        Dihitung {{ $rk['dihitung'] ?? 0 }} · cocok {{ $rk['cocok'] ?? 0 }} · kurang {{ $rk['kurang'] ?? 0 }} · lebih {{ $rk['lebih'] ?? 0 }}
        · Akurasi <strong>{{ $o->akurasi !== null ? $A($o->akurasi) . '%' : '—' }}</strong>
        · Kurang <span class="text-danger">{{ $Rp($rk['nilai_kurang'] ?? 0) }}</span> · Lebih {{ $Rp($rk['nilai_lebih'] ?? 0) }}
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-body py-2 small">
        <div class="row g-2">
            <div class="col-6 col-md-3"><span class="text-muted">Dihitung</span><br><strong>{{ $sudah }} / {{ $aktif->count() }}</strong> barang</div>
            <div class="col-6 col-md-3"><span class="text-muted">Hitung buta</span><br><strong>{{ $o->hitung_buta ? 'Nyala' : 'Mati' }}</strong> <x-tooltip key="stok_opname.hitung_buta" /></div>
            <div class="col-12 col-md-6"><span class="text-muted">Penghitung</span><br>{{ $o->penghitung->pluck('name')->implode(', ') ?: '-' }}</div>
        </div>
        @if($o->catatan)<div class="mt-1 text-muted">Catatan: {{ $o->catatan }}</div>@endif
    </div>
</div>

{{-- Tombol aksi --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @if($editable && $bolehAjukan)
    <form method="POST" action="{{ route('stok-opname.ajukan', $o) }}" onsubmit="return confirm('Ajukan opname ini untuk disetujui? Jumlah & rincian tidak bisa diubah lagi kecuali ditolak.')">
        @csrf
        <button class="btn btn-warning btn-sm" @disabled(count($masalah) > 0)><i class="bi bi-send me-1"></i>Ajukan Persetujuan</button>
    </form>
    @endif
    @if($o->status === 'diajukan' && $bolehSetujui)
        @if($ikutHitung)
        <span class="small text-muted align-self-center"><i class="bi bi-info-circle me-1"></i>Anda ikut menghitung opname ini, jadi persetujuan harus oleh orang lain.</span>
        @else
        <form method="POST" action="{{ route('stok-opname.setujui', $o) }}" onsubmit="return confirm('Setujui opname ini? Stok dan keuangan akan disesuaikan sekarang dan opname dikunci.')">
            @csrf
            <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Setujui</button>
        </form>
        @endif
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalTolak"><i class="bi bi-x-circle me-1"></i>Tolak</button>
    @endif
    @if($o->berjalan() && $bolehBatal)
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBatal"><i class="bi bi-slash-circle me-1"></i>Batalkan Opname</button>
    @endif
    @if($editable && $lihatSistem)
    <a href="{{ route('stok-opname.show', $o) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Muat ulang selisih</a>
    @endif
</div>

@if($masalah)
<div class="alert alert-warning py-2 small">
    <strong>Sebelum bisa diajukan:</strong>
    <ul class="mb-0 ps-3">@foreach($masalah as $m)<li>{{ $m }}</li>@endforeach</ul>
</div>
@endif

@if($editable && $bolehHitung)
<div class="card mb-2">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-12 col-md-6">
                <label class="form-label small mb-0">Scan / ketik kode barang lalu Enter (+1) <x-tooltip key="stok_opname.scan" /></label>
                <input type="text" id="inputScan" class="form-control" autocomplete="off" placeholder="Kode barang…">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small mb-0">Cari di daftar</label>
                <input type="search" id="cariBaris" class="form-control" placeholder="Nama atau kode…">
            </div>
        </div>
        <div id="pesanScan" class="small mt-1"></div>
    </div>
</div>
@else
<div class="mb-2"><input type="search" id="cariBaris" class="form-control form-control-sm" placeholder="Cari nama atau kode…"></div>
@endif

@if($lihatSistem)
<div class="btn-group btn-group-sm mb-2" role="group" id="saringan">
    <button type="button" class="btn btn-outline-secondary active" data-saring="semua">Semua</button>
    <button type="button" class="btn btn-outline-secondary" data-saring="belum">Belum dihitung</button>
    <button type="button" class="btn btn-outline-secondary" data-saring="selisih">Ada selisih</button>
</div>
@endif

<div class="card">
<div class="list-group list-group-flush" id="daftarBaris">
@foreach($rows as $r)
    @php
        $s = $lihatSistem ? $r->selisih() : null;
        $item = $r->item;
        $desimal = \App\Services\StokOpnameService::bolehDesimal($item?->satuan);
        $rincianKurang = $r->rincian->filter(fn ($x) => $x->penyebab !== \App\Enums\PenyebabSelisihStok::Lebih);
        $totalRincian = (float) $rincianKurang->sum('qty');
        $rincianJs = $rincianKurang->map(fn ($x) => ['id' => $x->id, 'penyebab' => $x->penyebab->value, 'qty' => (float) $x->qty,
            'catatan' => $x->catatan, 'foto' => $x->foto_path ? url('/img/' . $x->foto_path) : null])->values();
    @endphp
    <div class="list-group-item py-2 baris-opname {{ $r->dikeluarkan ? 'bg-light text-muted' : '' }}"
         data-item="{{ $r->item_id }}" data-cari="{{ mb_strtolower($item?->nama_item . ' ' . $item?->kode_item) }}"
         data-dihitung="{{ $r->sudahDihitung() ? 1 : 0 }}" data-selisih="{{ $s !== null && abs($s) > 0.0005 ? 1 : 0 }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold">{{ $item?->nama_item }}
                    @if($r->hitung_ulang)<span class="badge bg-danger">Hitung ulang</span>@endif
                    @if($r->dikeluarkan)<span class="badge bg-secondary">Tidak dihitung</span>@endif
                </div>
                <div class="small text-muted">{{ $item?->kode_item }} · {{ $item?->satuan }}{{ $desimal ? ' (boleh desimal)' : '' }}
                    @if($r->dihitung_at) · <span class="info-hitung">dihitung {{ $r->penghitung?->name }} {{ $r->dihitung_at->format('d/m H:i') }}</span>@else · <span class="info-hitung"></span>@endif
                </div>
                @if($r->dikeluarkan)<div class="small">Alasan: {{ $r->alasan_dikeluarkan }}</div>@endif
                @if($lihatSistem && $r->qty_sistem !== null && ! $r->dikeluarkan)
                <div class="small">
                    Sistem saat dihitung: <strong>{{ $A($r->qty_sistem) }}</strong>
                    @if($s !== null)
                    · Selisih: <strong class="{{ $s < 0 ? 'text-danger' : ($s > 0 ? 'text-success' : '') }}">{{ $s > 0 ? '+' : '' }}{{ $A($s) }}</strong>
                    @endif
                    @if($o->status === 'disetujui' && $r->nilai_selisih !== null && abs((float) $r->nilai_selisih) > 0)
                    · Nilai {{ (float) $r->nilai_selisih < 0 ? '−' : '+' }}{{ $Rp($r->nilai_selisih) }}
                    @elseif(isset($perkiraan[$r->id]))
                    · Perkiraan nilai {{ $perkiraan[$r->id] < 0 ? '−' : '+' }}{{ $Rp($perkiraan[$r->id]) }}
                    @endif
                </div>
                @if($r->rincian->isNotEmpty())
                <div class="small">
                    @foreach($r->rincian as $rin)
                    <span class="badge bg-light text-dark border">{{ $rin->penyebab->labelPendek() }} {{ $A($rin->qty) }}@if($rin->nilai !== null) · {{ $Rp($rin->nilai) }}@endif</span>
                    @if($rin->foto_path)<a href="{{ url('/img/' . $rin->foto_path) }}" target="_blank" class="small"><i class="bi bi-image"></i></a>@endif
                    @endforeach
                </div>
                @endif
                @endif
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                @if($editable && $bolehHitung && ! $r->dikeluarkan)
                <input type="text" inputmode="{{ $desimal ? 'decimal' : 'numeric' }}" class="form-control form-control-sm text-end input-fisik" style="width:110px"
                       value="{{ $r->qty_fisik !== null && ! $r->hitung_ulang ? $A($r->qty_fisik) : '' }}" placeholder="Fisik" data-item="{{ $r->item_id }}" aria-label="Jumlah fisik {{ $item?->nama_item }}">
                <span class="status-simpan small text-muted" style="width:18px"></span>
                @elseif(! $r->dikeluarkan)
                <span class="fw-semibold">{{ $r->sudahDihitung() ? $A($r->qty_fisik) : '—' }}</span>
                @endif
            </div>
        </div>
        @if($editable && $bolehPeriksa)
        <div class="d-flex flex-wrap gap-1 mt-1">
            @if($s !== null && $s < -0.0005 && ! $r->dikeluarkan && $r->sudahDihitung())
            <button type="button" class="btn btn-xs btn-sm py-0 {{ abs($totalRincian - abs($s)) > 0.0005 ? 'btn-danger' : 'btn-outline-success' }} btn-rincian"
                    data-item="{{ $r->item_id }}" data-nama="{{ $item?->nama_item }}" data-satuan="{{ $item?->satuan }}" data-target="{{ abs($s) }}"
                    data-rincian='@json($rincianJs)'>
                <i class="bi bi-list-check me-1"></i>{{ abs($totalRincian - abs($s)) > 0.0005 ? 'Rincikan penyebab' : 'Ubah rincian' }}
            </button>
            @endif
            @if($r->qty_fisik !== null && ! $r->hitung_ulang && ! $r->dikeluarkan)
            <form method="POST" action="{{ route('stok-opname.hitung-ulang', $o) }}">
                @csrf <input type="hidden" name="item_id" value="{{ $r->item_id }}">
                <button class="btn btn-sm py-0 btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i>Minta hitung ulang</button>
            </form>
            @endif
            @if($r->dikeluarkan)
            <form method="POST" action="{{ route('stok-opname.keluarkan', $o) }}">
                @csrf <input type="hidden" name="item_id" value="{{ $r->item_id }}"><input type="hidden" name="keluar" value="0">
                <button class="btn btn-sm py-0 btn-outline-secondary">Masukkan lagi</button>
            </form>
            @else
            <button type="button" class="btn btn-sm py-0 btn-outline-secondary btn-keluarkan" data-item="{{ $r->item_id }}" data-nama="{{ $item?->nama_item }}">Tidak dihitung</button>
            @endif
        </div>
        @endif
    </div>
@endforeach
</div>
</div>

{{-- ===== Modal (semua di luar form lain — CLAUDE.md 4.14) ===== --}}
@if($editable && $bolehPeriksa)
<div class="modal fade" id="modalRincian" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="POST" action="{{ route('stok-opname.rincian', $o) }}" enctype="multipart/form-data" class="modal-content" id="formRincian">
            @csrf
            <input type="hidden" name="item_id" id="rincianItem">
            <div class="modal-header">
                <h6 class="modal-title">Rincian penyebab — <span id="rincianNama"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small mb-2">Kurang <strong id="rincianTarget"></strong> <span id="rincianSatuan"></span>. Jumlah rincian harus sama dengan selisih.
                    Foto wajib untuk Rusak, Basi/Kedaluwarsa, Freezer mati, dan untuk Hilang senilai ≥ Rp {{ number_format($batasFoto, 0, ',', '.') }}{{ $batasFoto <= 0 ? ' (semua barang hilang)' : '' }}. <x-tooltip key="stok_opname.penyebab" /></div>
                <div id="rincianBaris"></div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="tambahRincian"><i class="bi bi-plus"></i> Tambah penyebab</button>
                <div class="small mt-2">Total rincian: <strong id="rincianTotal">0</strong></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-primary">Simpan Rincian</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalKeluarkan" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('stok-opname.keluarkan', $o) }}" class="modal-content">
            @csrf
            <input type="hidden" name="item_id" id="keluarkanItem"><input type="hidden" name="keluar" value="1">
            <div class="modal-header"><h6 class="modal-title">Keluarkan dari opname — <span id="keluarkanNama"></span></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">Barang ini tidak dihitung dan stoknya tidak disesuaikan. Gunakan kalau barang memang tidak bisa dihitung sekarang.</p>
                <input type="text" name="alasan" class="form-control" required maxlength="255" placeholder="Alasan, mis. barang sedang di kendaraan">
            </div>
            <div class="modal-footer"><button class="btn btn-secondary">Keluarkan</button></div>
        </form>
    </div>
</div>
@endif
@if($o->status === 'diajukan' && $bolehSetujui)
<div class="modal fade" id="modalTolak" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('stok-opname.tolak', $o) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h6 class="modal-title">Tolak opname {{ $o->nomor }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">Opname kembali ke tahap hitung. Tulis apa yang perlu diperbaiki.</p>
                <textarea name="alasan" class="form-control" rows="3" required maxlength="500"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Tolak</button></div>
        </form>
    </div>
</div>
@endif
@if($o->berjalan() && $bolehBatal)
<div class="modal fade" id="modalBatal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('stok-opname.batal', $o) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h6 class="modal-title">Batalkan opname {{ $o->nomor }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small">Stok dan keuangan tidak berubah. Hasil hitungan tetap tersimpan sebagai arsip.</p>
                <textarea name="alasan" class="form-control" rows="3" required maxlength="500" placeholder="Alasan pembatalan"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary">Batalkan Opname</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const URL_HITUNG = @json(route('stok-opname.hitung', $o));
    const URL_SCAN = @json(route('stok-opname.scan', $o));
    const PENYEBAB = @json($penyebabJs);
    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    async function kirim(url, data) {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) });
        const json = await res.json().catch(() => ({ ok: false, pesan: 'Gagal tersambung ke server.' }));
        if (!res.ok || !json.ok) throw new Error(json.pesan || json.message || 'Gagal menyimpan.');
        return json;
    }

    function tandai(baris, json) {
        const input = baris.querySelector('.input-fisik');
        if (input && document.activeElement !== input) input.value = json.teks_fisik === '—' ? '' : json.teks_fisik;
        const st = baris.querySelector('.status-simpan');
        if (st) { st.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>'; st.title = 'Tersimpan ' + (json.jam || ''); }
        const info = baris.querySelector('.info-hitung');
        if (info && json.jam) info.textContent = 'tersimpan ' + json.jam;
        baris.dataset.dihitung = json.dihitung ? 1 : 0;
    }

    // Simpan otomatis tiap jumlah diubah (jeda 600 ms)
    const tunda = {};
    document.querySelectorAll('.input-fisik').forEach(input => {
        const simpan = async () => {
            const baris = input.closest('.baris-opname');
            const st = baris.querySelector('.status-simpan');
            st.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                tandai(baris, await kirim(URL_HITUNG, { item_id: +input.dataset.item, qty: input.value.trim() }));
                input.classList.remove('is-invalid');
            } catch (e) {
                st.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                st.title = e.message;
                input.classList.add('is-invalid');
                alert(e.message);
            }
        };
        input.addEventListener('input', () => { clearTimeout(tunda[input.dataset.item]); tunda[input.dataset.item] = setTimeout(simpan, 600); });
        input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(tunda[input.dataset.item]); simpan(); } });
    });

    // Scan / ketik kode + Enter = +1
    const scan = document.getElementById('inputScan');
    if (scan) {
        scan.addEventListener('keydown', async e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const kode = scan.value.trim();
            if (!kode) return;
            const pesan = document.getElementById('pesanScan');
            try {
                const json = await kirim(URL_SCAN, { kode });
                const baris = document.querySelector(`.baris-opname[data-item="${json.item_id}"]`);
                if (baris) { tandai(baris, json); baris.classList.add('bg-success-subtle'); setTimeout(() => baris.classList.remove('bg-success-subtle'), 800); }
                pesan.className = 'small mt-1 text-success';
                pesan.textContent = `${json.nama}: ${json.teks_fisik}`;
            } catch (err) {
                pesan.className = 'small mt-1 text-danger';
                pesan.textContent = err.message;
            }
            scan.value = '';
        });
    }

    // Cari & saring
    let saring = 'semua';
    function terapkan() {
        const q = (document.getElementById('cariBaris')?.value || '').toLowerCase();
        document.querySelectorAll('.baris-opname').forEach(b => {
            let tampil = !q || b.dataset.cari.includes(q);
            if (saring === 'belum') tampil = tampil && b.dataset.dihitung === '0';
            if (saring === 'selisih') tampil = tampil && b.dataset.selisih === '1';
            b.style.display = tampil ? '' : 'none';
        });
    }
    document.getElementById('cariBaris')?.addEventListener('input', terapkan);
    document.querySelectorAll('#saringan [data-saring]').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('#saringan [data-saring]').forEach(x => x.classList.remove('active'));
        btn.classList.add('active'); saring = btn.dataset.saring; terapkan();
    }));

    // Rincian penyebab
    const modalR = document.getElementById('modalRincian');
    if (modalR) {
        const box = document.getElementById('rincianBaris');
        let n = 0, target = 0;
        const total = () => {
            const t = [...box.querySelectorAll('.qty-rincian')].reduce((a, i) => a + (parseFloat(String(i.value).replace(',', '.')) || 0), 0);
            const el = document.getElementById('rincianTotal');
            el.textContent = (Math.round(t * 1000) / 1000).toLocaleString('id-ID') + ' / ' + target.toLocaleString('id-ID');
            el.className = Math.abs(t - target) < 0.0005 ? 'text-success' : 'text-danger';
        };
        const tambah = (d = {}) => {
            const i = n++;
            const div = document.createElement('div');
            div.className = 'border rounded p-2 mb-2';
            div.innerHTML = `<div class="row g-2 align-items-end">
                <div class="col-12 col-md-4"><label class="form-label small mb-0">Penyebab</label>
                    <select name="rincian[${i}][penyebab]" class="form-select form-select-sm" required><option value="">— pilih —</option>${PENYEBAB.map(p => `<option value="${p.v}" ${p.v === d.penyebab ? 'selected' : ''}>${esc(p.l)}</option>`).join('')}</select></div>
                <div class="col-5 col-md-2"><label class="form-label small mb-0">Jumlah</label>
                    <input type="text" inputmode="decimal" name="rincian[${i}][qty]" class="form-control form-control-sm qty-rincian" value="${d.qty ?? ''}" required></div>
                <div class="col-7 col-md-3"><label class="form-label small mb-0">Foto ${d.foto ? `<a href="${esc(d.foto)}" target="_blank">(lihat)</a>` : ''}</label>
                    <input type="file" name="rincian[${i}][foto]" accept="image/*" capture="environment" class="form-control form-control-sm"></div>
                <div class="col-10 col-md-2"><label class="form-label small mb-0">Catatan</label>
                    <input type="text" name="rincian[${i}][catatan]" class="form-control form-control-sm" maxlength="255" value="${esc(d.catatan)}"></div>
                <div class="col-2 col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm hapus-rincian" title="Hapus"><i class="bi bi-trash"></i></button></div>
                ${d.id ? `<input type="hidden" name="rincian[${i}][rincian_id]" value="${d.id}">` : ''}
            </div>`;
            box.appendChild(div);
            div.querySelector('.qty-rincian').addEventListener('input', total);
            div.querySelector('.hapus-rincian').addEventListener('click', () => { div.remove(); total(); });
        };
        document.getElementById('tambahRincian').addEventListener('click', () => { tambah(); total(); });
        document.querySelectorAll('.btn-rincian').forEach(btn => btn.addEventListener('click', () => {
            box.innerHTML = ''; n = 0;
            target = parseFloat(btn.dataset.target);
            document.getElementById('rincianItem').value = btn.dataset.item;
            document.getElementById('rincianNama').textContent = btn.dataset.nama;
            document.getElementById('rincianTarget').textContent = target.toLocaleString('id-ID');
            document.getElementById('rincianSatuan').textContent = btn.dataset.satuan;
            const ada = JSON.parse(btn.dataset.rincian || '[]');
            if (ada.length) ada.forEach(tambah); else tambah({ qty: target });
            total();
            bootstrap.Modal.getOrCreateInstance(modalR).show();
        }));
        document.querySelectorAll('.btn-keluarkan').forEach(btn => btn.addEventListener('click', () => {
            document.getElementById('keluarkanItem').value = btn.dataset.item;
            document.getElementById('keluarkanNama').textContent = btn.dataset.nama;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKeluarkan')).show();
        }));
    }
})();
</script>
@endpush
