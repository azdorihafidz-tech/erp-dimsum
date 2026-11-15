@extends('layouts.app')

@section('title', 'Stok Rusak')

@php
    $A = fn ($n) => \App\Services\StokOpnameService::angka($n === null ? null : (float) $n);
    $Rp = fn ($n) => 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
    $kelola = auth()->user()->hasPermission('stok_opname.stok_rusak');
@endphp

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-danger"></i>Stok Rusak</h5>
    <x-panduan-button slug="stok-rusak" />
</div>

<div class="alert alert-info small py-2">
    Barang rusak/basi/kena freezer mati dari <strong>Stok Opname</strong> yang disetujui ditahan di sini — tidak bisa dijual atau dipakai resep.
    Kerugiannya sudah dicatat saat opname disetujui. Tentukan tindak lanjutnya di bawah. <x-tooltip key="stok_rusak.tindakan" />
</div>

<form method="GET" class="card card-body py-2 mb-3">
    <div class="row g-2 align-items-end">
        @if($lokasi->count() > 1)
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Lokasi</label>
            <select name="cabang_id" class="form-select form-select-sm">
                <option value="">Semua lokasi</option>
                @foreach($lokasi as $l)<option value="{{ $l->id }}" @selected($filter == $l->id)>{{ $l->nama_cabang }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="tertahan" @selected($status === 'tertahan')>Masih tertahan</option>
                <option value="selesai" @selected($status === 'selesai')>Sudah selesai</option>
                <option value="semua" @selected($status === 'semua')>Semua</option>
            </select>
        </div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
        <div class="col-12 col-md text-md-end small">
            Tertahan: <strong>{{ (int) ($ringkas->baris ?? 0) }}</strong> baris · nilai <strong class="text-danger">{{ $Rp($ringkas->nilai ?? 0) }}</strong>
            @if($ringkas?->tertua) · tertua {{ \Carbon\Carbon::parse($ringkas->tertua)->diffInDays(today()) }} hari @endif
        </div>
    </div>
</form>

<div class="card">
<div class="list-group list-group-flush">
@forelse($rows as $s)
    @php $o = $s->rincian?->opnameItem?->opname; @endphp
    <div class="list-group-item py-2">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div class="min-w-0">
                <div class="fw-semibold">{{ $s->item?->nama_item }} <span class="badge bg-secondary">{{ $s->penyebab->labelPendek() }}</span>
                    @if($s->status === 'selesai')<span class="badge bg-success">Selesai</span>@endif</div>
                <div class="small text-muted">
                    {{ $s->cabang?->nama_cabang }} · sejak {{ $s->tanggal->format('d/m/Y') }} ({{ $s->umurHari() }} hari)
                    @if($o) · dari <a href="{{ route('stok-opname.show', $o) }}">{{ $o->nomor }}</a>@endif
                </div>
                <div class="small">Sisa <strong>{{ $A($s->qty_sisa) }}</strong> dari {{ $A($s->qty_awal) }} {{ $s->item?->satuan }} · harga pokok {{ $Rp($s->harga_pokok) }} · nilai sisa <strong>{{ $Rp($s->nilaiSisa()) }}</strong></div>
                @foreach($s->tindakan as $t)
                <div class="small text-muted">
                    <i class="bi bi-arrow-return-right"></i> {{ $t->tanggal->format('d/m/Y') }} — {{ \App\Services\StokRusakService::LABEL_TINDAKAN[$t->jenis] ?? $t->jenis }} {{ $A($t->qty) }}
                    @if((float) $t->uang > 0) · uang {{ $Rp($t->uang) }} ke {{ $t->kas?->nama_kas }}@endif
                    @if($t->nomor_po) · PO {{ $t->nomor_po }}@endif
                    · {{ $t->user?->name }}
                    @if($t->foto_path)<a href="{{ url('/img/' . $t->foto_path) }}" target="_blank"><i class="bi bi-image"></i></a>@endif
                    @if($t->nomor_ba)<a href="{{ route('stok-rusak.berita-acara', $t) }}"><i class="bi bi-file-earmark-pdf"></i> {{ $t->nomor_ba }}</a>@endif
                </div>
                @endforeach
            </div>
            @if($kelola && (float) $s->qty_sisa > 0)
            <div class="flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-primary btn-tindak"
                        data-url="{{ route('stok-rusak.tindak', $s) }}" data-nama="{{ $s->item?->nama_item }}" data-sisa="{{ (float) $s->qty_sisa }}"
                        data-satuan="{{ $s->item?->satuan }}" data-harga="{{ (float) $s->harga_pokok }}" data-cabang="{{ $s->cabang_id }}"
                        data-pilihan='@json(\App\Services\StokRusakService::pilihan($s))'>
                    <i class="bi bi-check2-square me-1"></i>Tindak lanjut
                </button>
            </div>
            @endif
        </div>
    </div>
@empty
    <div class="list-group-item text-center text-muted py-4">Tidak ada stok rusak{{ $status === 'tertahan' ? ' yang tertahan' : '' }}.</div>
@endforelse
</div>
</div>
<div class="mt-2">{{ $rows->links() }}</div>

@if($kelola)
<div class="modal fade" id="modalTindak" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <form method="POST" id="formTindak" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header"><h6 class="modal-title">Tindak lanjut — <span id="tNama"></span></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small mb-0">Tindakan</label>
                    <select name="jenis" id="tJenis" class="form-select" required></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Jumlah (sisa <span id="tSisa"></span> <span id="tSatuan"></span>)</label>
                    <input type="text" inputmode="decimal" name="qty" id="tQty" class="form-control" required>
                    <div class="form-text">Nilai barang: <span id="tNilai">-</span></div>
                </div>
                <div class="mb-2 grup grup-pemasok">
                    <label class="form-label small mb-0">Pemasok</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($suppliers as $sp)<option value="{{ $sp->id }}">{{ $sp->nama_supplier }}</option>@endforeach
                    </select>
                </div>
                <div class="mb-2 grup grup-uang">
                    <label class="form-label small mb-0">Uang diterima (Rp)</label>
                    <input type="text" inputmode="numeric" name="uang" id="tUang" class="form-control">
                </div>
                <div class="mb-2 grup grup-uang">
                    <label class="form-label small mb-0" id="tKasLabel">Masuk ke kas</label>
                    <select name="kas_id" id="tKas" class="form-select form-select-sm"></select>
                </div>
                <div class="mb-2 grup grup-po">
                    <label class="form-label small mb-0">Nomor PO / faktur <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_po" class="form-control form-control-sm" maxlength="60">
                    <div class="form-text">Catat sebagai uang masuk ke kas yang dipakai membayar PO itu. Bayar PO-nya tetap penuh di sistem, jadi kas bersihnya sama dengan uang yang benar-benar keluar.</div>
                </div>
                <div class="mb-2 grup grup-musnah">
                    <label class="form-label small mb-0">Saksi</label>
                    <input type="text" name="saksi" class="form-control form-control-sm" maxlength="150" placeholder="Nama saksi pemusnahan">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Foto <span class="text-danger grup grup-musnah">* wajib</span></label>
                    <input type="file" name="foto" accept="image/*" capture="environment" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Catatan</label>
                    <input type="text" name="catatan" class="form-control form-control-sm" maxlength="255">
                </div>
                <div class="alert alert-light small mb-0" id="tInfo"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const KAS = @json($kas);
    const INFO = {
        ganti_barang: 'Barang pengganti masuk lagi ke stok jual dengan harga pokok semula. Kerugiannya dibatalkan (tanpa kas).',
        bayar_uang: 'Uang dari pemasok masuk ke kas yang dipilih dan dicatat sebagai Pemulihan Kerugian Stok.',
        potong_tagihan: 'Pemasok memotong tagihan PO. Dicatat sebagai uang masuk ke kas yang dipakai membayar PO itu (Pemulihan Kerugian Stok).',
        musnahkan: 'Barang keluar dari stok rusak. Tidak ada catatan keuangan baru (kerugian sudah dicatat saat opname). Berita Acara Pemusnahan dibuat otomatis.',
        pakai_lagi: 'Barang kembali ke stok jual dengan harga pokok semula. Kerugiannya dibatalkan (tanpa kas). Hanya untuk barang yang kemasannya rusak tapi isinya aman.',
    };
    const modal = document.getElementById('modalTindak');
    if (!modal) return;
    const jenis = document.getElementById('tJenis');
    let harga = 0;
    const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
    const fmt = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    function tampil() {
        const j = jenis.value;
        modal.querySelectorAll('.grup').forEach(el => el.style.display = 'none');
        const on = c => modal.querySelectorAll('.' + c).forEach(el => el.style.display = '');
        if (['ganti_barang', 'bayar_uang', 'potong_tagihan'].includes(j)) on('grup-pemasok');
        if (['bayar_uang', 'potong_tagihan'].includes(j)) on('grup-uang');
        if (j === 'potong_tagihan') on('grup-po');
        if (j === 'musnahkan') on('grup-musnah');
        document.getElementById('tKasLabel').textContent = j === 'potong_tagihan' ? 'Kas yang dipakai membayar PO' : 'Masuk ke kas';
        document.getElementById('tInfo').textContent = INFO[j] || '';
    }
    function nilai() {
        const q = parseFloat(String(document.getElementById('tQty').value).replace(',', '.')) || 0;
        document.getElementById('tNilai').textContent = fmt(q * harga);
    }
    jenis.addEventListener('change', tampil);
    document.getElementById('tQty').addEventListener('input', nilai);
    document.querySelectorAll('.btn-tindak').forEach(btn => btn.addEventListener('click', () => {
        const f = document.getElementById('formTindak');
        f.reset();
        f.action = btn.dataset.url;
        harga = parseFloat(btn.dataset.harga) || 0;
        document.getElementById('tNama').textContent = btn.dataset.nama;
        document.getElementById('tSisa').textContent = parseFloat(btn.dataset.sisa).toLocaleString('id-ID');
        document.getElementById('tSatuan').textContent = btn.dataset.satuan;
        document.getElementById('tQty').value = btn.dataset.sisa;
        const pilihan = JSON.parse(btn.dataset.pilihan || '{}');
        jenis.innerHTML = Object.entries(pilihan).map(([v, l]) => `<option value="${v}">${esc(l)}</option>`).join('');
        document.getElementById('tKas').innerHTML = (KAS[btn.dataset.cabang] || []).map(k => `<option value="${k.id}">${esc(k.nama)}</option>`).join('') || '<option value="">(belum ada kas)</option>';
        tampil(); nilai();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
})();
</script>
@endpush
