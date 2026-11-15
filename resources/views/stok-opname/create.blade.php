@extends('layouts.app')

@section('title', 'Mulai Stok Opname')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus me-2 text-primary"></i>Mulai Stok Opname</h5>
    <x-panduan-button slug="stok-opname" />
</div>

@if($lokasi->isEmpty())
<div class="alert alert-warning">Anda belum punya akses ke lokasi mana pun.</div>
@else
<form method="POST" action="{{ route('stok-opname.store') }}" id="formMulai" class="card card-body">
    @csrf
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label">Lokasi <span class="text-danger">*</span> <x-tooltip key="stok_opname.lokasi" /></label>
            <select name="cabang_id" id="cabangId" class="form-select" required>
                @foreach($lokasi as $l)
                <option value="{{ $l->id }}" @selected($lokasiId == $l->id)>{{ $l->nama_cabang }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label">Cakupan <span class="text-danger">*</span> <x-tooltip key="stok_opname.cakupan" /></label>
            <select name="cakupan" id="cakupan" class="form-select">
                <option value="semua" @selected(old('cakupan', 'semua') === 'semua')>Semua barang di lokasi ini</option>
                <option value="kategori" @selected(old('cakupan') === 'kategori')>Per kategori</option>
                <option value="barang" @selected(old('cakupan') === 'barang')>Barang tertentu</option>
            </select>
        </div>

        <div class="col-12" id="boxKategori" style="display:none">
            <label class="form-label">Kategori</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach($kategori as $k)
                <label class="border rounded px-2 py-1 small"><input type="checkbox" class="form-check-input me-1 cek-kategori" name="cakupan_ids[]" value="{{ $k->id }}"
                    @checked(old('cakupan') === 'kategori' && in_array($k->id, old('cakupan_ids', [])))> {{ $k->nama_kategori }}</label>
                @endforeach
            </div>
        </div>

        <div class="col-12" id="boxBarang" style="display:none">
            <label class="form-label">Barang</label>
            <input type="search" id="cariBarang" class="form-control form-control-sm mb-2" placeholder="Cari nama atau kode barang…">
            <div id="daftarBarang" class="border rounded p-2" style="max-height:280px;overflow-y:auto"></div>
        </div>

        <div class="col-12">
            <label class="form-label">Penghitung <x-tooltip key="stok_opname.penghitung" /></label>
            <div id="daftarPenghitung" class="d-flex flex-wrap gap-2"></div>
            <div class="form-text">Kosong = Anda sendiri yang menghitung.</div>
        </div>

        <div class="col-12">
            <label class="form-label">Catatan</label>
            <input type="text" name="catatan" value="{{ old('catatan') }}" class="form-control" maxlength="500" placeholder="mis. Opname akhir bulan November">
        </div>

        <div class="col-12">
            <div class="alert alert-info small mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Hitung buta <strong>{{ $hitungButa ? 'NYALA' : 'MATI' }}</strong> (diatur di Pengaturan Umum):
                {{ $hitungButa ? 'penghitung tidak melihat stok sistem.' : 'penghitung bisa melihat stok sistem.' }}
                Lokasi tetap boleh berjualan selama opname — yang diterapkan saat disetujui hanya <strong>selisihnya</strong>.
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Mulai Opname</button>
        <a href="{{ route('stok-opname.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const BARANG = @json($barangPerLokasi);
    const PENGHITUNG = @json($penghitungPerLokasi);
    const OLD_BARANG = @json(old('cakupan') === 'barang' ? array_map('intval', old('cakupan_ids', [])) : []);
    const OLD_PENGHITUNG = @json(array_map('intval', old('penghitung', [])));
    const cabang = document.getElementById('cabangId');
    const cakupan = document.getElementById('cakupan');
    if (!cabang) return;

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // Semua barang lokasi dirender sekali; pencarian hanya menyembunyikan (centangan tetap terkirim)
    function renderBarang() {
        const list = BARANG[cabang.value] || [];
        const box = document.getElementById('daftarBarang');
        box.innerHTML = list.map(b =>
            `<label class="d-flex align-items-center gap-2 py-1 small baris-barang ${b.terkunci ? 'text-muted' : ''}" data-cari="${esc((b.nama + ' ' + (b.kode || '')).toLowerCase())}">
                <input type="checkbox" class="form-check-input m-0" name="cakupan_ids[]" value="${b.id}" ${OLD_BARANG.includes(b.id) ? 'checked' : ''} ${b.terkunci ? 'disabled' : ''}>
                <span>${esc(b.nama)} <span class="text-muted">${esc(b.kode)}</span>${b.terkunci ? ` <span class="badge bg-secondary">sedang di ${esc(b.terkunci)}</span>` : ''}</span>
            </label>`).join('') || '<div class="text-muted small">Tidak ada barang.</div>';
        OLD_BARANG.length = 0;
        saring();
    }

    function saring() {
        const q = (document.getElementById('cariBarang').value || '').toLowerCase();
        document.querySelectorAll('#daftarBarang .baris-barang').forEach(el => {
            el.style.display = !q || el.dataset.cari.includes(q) ? '' : 'none';
        });
    }

    function renderPenghitung() {
        const box = document.getElementById('daftarPenghitung');
        box.innerHTML = (PENGHITUNG[cabang.value] || []).map(u =>
            `<label class="border rounded px-2 py-1 small"><input type="checkbox" class="form-check-input me-1" name="penghitung[]" value="${u.id}" ${OLD_PENGHITUNG.includes(u.id) ? 'checked' : ''}> ${esc(u.nama)}</label>`
        ).join('') || '<span class="text-muted small">Belum ada user berizin Hitung Stok Opname di lokasi ini.</span>';
    }

    function toggle() {
        document.getElementById('boxKategori').style.display = cakupan.value === 'kategori' ? '' : 'none';
        document.getElementById('boxBarang').style.display = cakupan.value === 'barang' ? '' : 'none';
        document.querySelectorAll('.cek-kategori').forEach(c => c.disabled = cakupan.value !== 'kategori');
        document.getElementById('boxBarang').querySelectorAll('input[type=checkbox]').forEach(c => {
            c.disabled = cakupan.value !== 'barang' || c.closest('.text-muted') !== null;
        });
    }

    cabang.addEventListener('change', () => { renderBarang(); renderPenghitung(); toggle(); });
    cakupan.addEventListener('change', toggle);
    document.getElementById('cariBarang').addEventListener('input', saring);
    renderBarang(); renderPenghitung(); toggle();
})();
</script>
@endpush
