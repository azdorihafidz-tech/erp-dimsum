{{--
    Tahap 2.5 D'mentai — form bersama create/edit Produk Jual.
    Variabel wajib: $formAction, $formMethod ('POST'|'PUT'), $item (null|Item),
    $categories, $cabangList, $bahanOptions.
--}}
@php
    $isEdit = $item !== null;
    $resepAwal = $isEdit ? $item->resep?->items->map(fn($ri) => [
        'item_id' => $ri->item_id, 'nama' => $ri->item?->nama_item, 'qty_per_unit' => $ri->qty_per_unit,
        'satuan' => $ri->satuan, 'is_wajib' => $ri->is_wajib, 'mode_harga' => $ri->mode_harga,
        // Fitur Import dari Bumbu Pusat (2026-09-17)
        'resep_bumbu_ref_id' => $ri->resep_bumbu_ref_id,
        'bumbu_nama' => $ri->resepBumbuRef?->nama,
    ])->values() : collect();
    $atributAwal = $isEdit ? $item->attributes->map(fn($a) => [
        'nama' => $a->nama, 'nilai' => $a->values->pluck('nilai')->implode(', '),
    ])->values() : collect();
    $variantAwal = $isEdit ? $item->variants->map(fn($v) => [
        'signature' => $v->attributeValues->pluck('nilai')->sort()->implode('|'),
        'harga_override' => $v->harga_override,
    ])->values() : collect();
    $cabangAwal = $isEdit ? $item->itemCabang->keyBy('cabang_id') : collect();
    $bahanOptionsJs = $bahanOptions->map(fn($b) => [
        'id' => $b->id, 'nama' => $b->nama_item, 'satuan' => $b->satuan,
        'harga' => (float) ($b->harga_beli_terakhir ?? 0),
    ])->values();
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" id="formProdukJual">
@csrf
@if($formMethod === 'PUT') @method('PUT') @endif

<div class="row g-4">
    <div class="col-12 col-lg-8">

        {{-- SECTION 1: Info Dasar --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-info-circle me-1"></i>Informasi Dasar</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Foto Produk <x-tooltip key="master_produk_jual.foto" /></label>
                        <div class="mb-2" style="width:120px;height:120px;background:#f8fafc;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                            <img id="fotoPreview" src="{{ $isEdit && $item->foto ? asset('storage/'.$item->foto) : '' }}"
                                style="width:100%;height:100%;object-fit:cover;{{ $isEdit && $item->foto ? '' : 'display:none' }}">
                            <span id="fotoPlaceholder" style="font-size:2.5rem;{{ $isEdit && $item->foto ? 'display:none' : '' }}">🥟</span>
                        </div>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm @error('foto') is-invalid @enderror"
                            onchange="previewFoto(this)">
                        @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" style="font-size:0.72rem">Max 2MB, auto-resize ke 800x800px.</div>
                        @if($isEdit && $item->foto)
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="hapus_foto">
                            <label class="form-check-label small text-danger" for="hapus_foto">Hapus foto</label>
                        </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Kode Item *</label>
                                <input type="text" name="kode_item" class="form-control @error('kode_item') is-invalid @enderror"
                                    style="text-transform:uppercase" value="{{ old('kode_item', $item->kode_item ?? '') }}" required>
                                @error('kode_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Tipe *</label>
                                <select name="tipe" class="form-select @error('tipe') is-invalid @enderror" required>
                                    <option value="produk_jual" @selected(old('tipe', $item->tipe ?? 'produk_jual')=='produk_jual')>Produk Jual (grid utama)</option>
                                    <option value="produk_tambahan" @selected(old('tipe', $item->tipe ?? '')=='produk_tambahan')>Produk Tambahan (add-on berbayar)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Nama Produk *</label>
                                <input type="text" name="nama_item" class="form-control @error('nama_item') is-invalid @enderror"
                                    value="{{ old('nama_item', $item->nama_item ?? '') }}" required>
                                @error('nama_item')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Kategori</label>
                                <select name="item_category_id" class="form-select">
                                    <option value="">— Pilih —</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('item_category_id', $item->item_category_id ?? null)==$cat->id)>{{ $cat->nama_kategori }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold">Satuan *</label>
                                <input type="text" name="satuan" class="form-control @error('satuan') is-invalid @enderror"
                                    value="{{ old('satuan', $item->satuan ?? 'pcs') }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Harga Jual (default) *</label>
                        <x-input-rupiah name="harga_jual" :value="old('harga_jual', $item->harga_jual ?? 0)" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Deskripsi (utk struk)</label>
                        <textarea name="deskripsi" rows="2" class="form-control">{{ old('deskripsi', $item->deskripsi ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Komposisi / Resep --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-1"></i>Komposisi / Resep (opsional) <x-tooltip key="master_produk_jual.resep" /></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bukaModalImportBumbu()">
                        <i class="bi bi-box-arrow-in-down"></i> Import dari Bumbu Pusat
                    </button> <x-tooltip key="master_produk_jual.import_bumbu" />
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="tambahBarisResep()">
                        <i class="bi bi-plus-lg"></i> Tambah Bahan
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-sm align-middle mb-2" id="tabelResep">
                    <thead>
                        <tr>
                            <th>Bahan</th><th style="width:100px">Qty/unit</th><th style="width:90px">Satuan</th><th style="width:70px">Wajib</th>
                            <th style="width:130px">Harga Master <x-tooltip key="master_produk_jual.harga_master_preview" /></th>
                            <th style="width:120px">Subtotal</th>
                            <th style="width:110px">Mode Harga <x-tooltip key="master_produk_jual.mode_harga" /></th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="bodyResep"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Total HPP (Preview Cepat)</td>
                            <td class="fw-semibold" id="totalHppFooter">Rp 0</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="small text-muted mb-1">Preview Cepat: instant, client-side, tanpa konversi satuan otomatis.</div>
                <div class="alert alert-warning py-2 small mb-2" id="warningBarisLinked" hidden>
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Total di atas belum termasuk baris Bumbu Pusat (🧂) — klik <strong>Simulasi Produksi Lengkap</strong> di bawah untuk total termasuk Bumbu Pusat.
                </div>
                <div class="alert alert-warning py-2 small mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Harga Master &amp; Subtotal murni preview live dari Master Bahan Baku (belum ada konversi satuan otomatis —
                    kalau angka terlihat aneh, mis. "Rp 45.000/gram", cek &amp; perbaiki harga/satuan bahan itu di menu
                    <a href="{{ route('master.bahan-baku.index') }}" target="_blank">Master Bahan Baku</a>.
                </div>
                @if($isEdit)
                <div class="border-top pt-2 mt-2">
                    <div class="input-group input-group-sm" style="max-width:320px">
                        <span class="input-group-text">Simulasi Produksi Lengkap <x-tooltip key="master_produk_jual.kalkulator" /></span>
                        <input type="number" id="jumlahProduksi" class="form-control" value="1" min="1">
                        <button type="button" class="btn btn-outline-secondary" onclick="hitungKalkulator()">Hitung</button>
                    </div>
                    <div class="small text-muted mt-1">Server-side, hitung dari isi form saat ini (belum perlu Simpan dulu), expand Bumbu Pusat penuh.</div>
                    <div id="hasilKalkulator" class="small text-muted mt-2"></div>
                </div>
                @endif
            </div>
        </div>

        {{-- SECTION 3: Varian --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="punya_varian" value="1" id="punyaVarian"
                        {{ old('punya_varian', $item->punya_varian ?? false) ? 'checked' : '' }} onchange="toggleVarianSection()">
                    <label class="form-check-label fw-semibold" for="punyaVarian"><i class="bi bi-diagram-3 me-1"></i>Punya Varian?</label> <x-tooltip key="master_produk_jual.punya_varian" />
                </div>
                <x-panduan-button slug="item-varian" />
            </div>
            <div class="card-body" id="varianSection" style="{{ old('punya_varian', $item->punya_varian ?? false) ? '' : 'display:none' }}">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="stok_per_varian" value="1" id="stokPerVarian"
                        {{ old('stok_per_varian', $item->stok_per_varian ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="stokPerVarian">Stok terpisah per varian</label>
                </div>

                <div id="bodyAtribut"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="tambahBarisAtribut()">
                    <i class="bi bi-plus-lg"></i> Tambah Atribut (mis. Size, Rasa)
                </button>

                <button type="button" class="btn btn-sm btn-secondary mb-2" onclick="regenerateKombinasi()">
                    <i class="bi bi-arrow-repeat me-1"></i>Update Preview Kombinasi
                </button>
                <table class="table table-sm" id="tabelKombinasi">
                    <thead><tr><th>Kombinasi</th><th style="width:150px">Harga Override</th></tr></thead>
                    <tbody id="bodyKombinasi"></tbody>
                </table>
                <div class="form-text" style="font-size:0.72rem">Kosongkan Harga Override untuk pakai Harga Jual default.</div>
            </div>
        </div>

        {{-- SECTION 4: Ketersediaan Outlet --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-shop me-1"></i>Ketersediaan Outlet <x-tooltip key="master_produk_jual.cabang_aktif" /></div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($cabangList as $cabang)
                    @php $cabangRow = $cabangAwal->get($cabang->id); @endphp
                    <div class="col-12 col-md-6">
                        <div class="d-flex align-items-center gap-2 p-2 border rounded">
                            <div class="form-check flex-shrink-0">
                                <input class="form-check-input" type="checkbox" name="cabang_aktif[]" value="{{ $cabang->id }}"
                                    id="cab{{ $cabang->id }}" {{ (!$isEdit || !$cabangRow || $cabangRow->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="cab{{ $cabang->id }}">{{ $cabang->nama_cabang }}</label>
                            </div>
                            <input type="number" name="cabang_harga[{{ $cabang->id }}]" class="form-control form-control-sm"
                                placeholder="Harga override" value="{{ $cabangRow?->harga_override }}">
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                        {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Aktif (tampil di POS)</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Produk</button>
                    <a href="{{ route('master.produk-jual.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>

        @if($isEdit)
        @can('master.produk_jual.delete')
        <div class="card mt-3 border-danger">
            <div class="card-body">
                <h6 class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle me-1"></i>Zona Berbahaya</h6>
                <button type="submit" form="formHapusProdukJual" class="btn btn-outline-danger btn-sm w-100"
                    onclick="return confirm('Hapus {{ addslashes($item->nama_item) }} secara permanen?')">
                    <i class="bi bi-trash me-1"></i>Hapus Produk
                </button>
            </div>
        </div>
        @endcan
        @endif
    </div>
</div>
</form>

{{--
    Tahap 7 D'mentai (Bug 3 fix, 2026-09-14) — form hapus WAJIB jadi SIBLING
    dari form utama, BUKAN nested di dalamnya. HTML tidak mengizinkan <form>
    bersarang: browser akan membuang tag <form> dalam tapi tetap memasukkan
    child input-nya (termasuk hidden _method=DELETE) ke form LUAR -- karena
    posisinya di DOM SETELAH hidden _method=PUT bawaan form utama, klik
    "Simpan Produk" ter-method-spoof jadi DELETE (nilai _method TERAKHIR yang
    menang di $_POST) dan produk ke-soft-delete alih-alih ter-update. Tombol
    hapus di atas pakai attribute `form="formHapusProdukJual"` (HTML5 form
    association) supaya tetap tampil visual di dalam card sidebar tanpa
    ikut jadi child DOM form ini.
--}}
@if($isEdit)
@can('master.produk_jual.delete')
<form method="POST" id="formHapusProdukJual" action="{{ route('master.produk-jual.destroy', $item) }}">
    @csrf @method('DELETE')
</form>
@endcan
@endif

{{-- Modal Import dari Bumbu Pusat (2026-09-17) --}}
<div class="modal fade" id="modalImportBumbu" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-1"></i>Import dari Bumbu Pusat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-sm mb-2" id="searchBumbuPusat"
                    placeholder="Cari nama bumbu..." oninput="cariBumbuPusat(this.value)">
                <div id="listBumbuPusat" class="list-group">
                    <div class="text-center text-muted py-3 small">Ketik untuk cari, atau tunggu daftar dimuat...</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const BAHAN_OPTIONS = @json($bahanOptionsJs);
const RESEP_AWAL = @json($resepAwal);
const ATRIBUT_AWAL = @json($atributAwal);
const VARIANT_AWAL = @json($variantAwal);
@if($isEdit)
const KALKULATOR_URL = @json(route('master.produk-jual.kalkulator-resep', $item));
@endif
const BUMBU_PUSAT_LIST_URL = @json(route('master.produk-jual.bumbu-pusat.list'));

let resepIdx = 0;
function tambahBarisResep(data) {
    data = data || {};
    if (data.resep_bumbu_ref_id) {
        tambahBarisResepLinked(data.resep_bumbu_ref_id, data.bumbu_nama, data);
        return;
    }
    const idx = resepIdx++;
    const opsi = BAHAN_OPTIONS.map(b => `<option value="${b.id}" data-satuan="${b.satuan}" data-harga="${b.harga}" ${data.item_id==b.id?'selected':''}>${b.nama}</option>`).join('');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="resep[${idx}][item_id]" class="form-select form-select-sm" onchange="onBahanResepBerubah(this)">
            <option value="">— Pilih Bahan —</option>${opsi}
        </select></td>
        <td><input type="number" step="0.001" min="0.001" name="resep[${idx}][qty_per_unit]" class="form-control form-control-sm qtyResep" value="${data.qty_per_unit ?? ''}" oninput="hitungPreviewBaris(this.closest('tr'))"></td>
        <td><input type="text" name="resep[${idx}][satuan]" class="form-control form-control-sm satuanResep" value="${data.satuan ?? ''}"></td>
        <td class="text-center"><input type="checkbox" name="resep[${idx}][is_wajib]" value="1" ${(data.is_wajib ?? true) ? 'checked' : ''}></td>
        <td class="small hargaMasterPreview text-muted">—</td>
        <td class="small subtotalPreview fw-semibold">—</td>
        <td><select name="resep[${idx}][mode_harga]" class="form-select form-select-sm">
            <option value="gratis" ${(data.mode_harga??'gratis')=='gratis'?'selected':''}>Gratis</option>
            <option value="pakai_master" ${data.mode_harga=='pakai_master'?'selected':''}>Pakai Master</option>
        </select></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusBarisResep(this)"><i class="bi bi-x"></i></button></td>
    `;
    document.getElementById('bodyResep').appendChild(tr);
    hitungPreviewBaris(tr);
}
RESEP_AWAL.forEach(r => tambahBarisResep(r));

function hapusBarisResep(btn) {
    btn.closest('tr').remove();
    hitungTotalHpp();
}

// ===== Live preview Harga Master & Subtotal (2026-09-19) =====
// SENGAJA belum ada konversi satuan (Sprint 2, lihat CLAUDE.md TODO) --
// takaran dikalikan APA ADANYA dgn harga master, supaya data anomali
// (mis. bahan di-satuan-kan salah di Master Bahan Baku) ketahuan user
// SEBELUM Simpan, bukan disembunyikan lewat "koreksi otomatis" yang
// justru bisa nutupin kesalahan input data aslinya.
function formatRupiahPreview(angka) {
    return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
}

function onBahanResepBerubah(select) {
    const tr = select.closest('tr');
    const satuanInput = tr.querySelector('.satuanResep');
    const opt = select.selectedOptions[0];
    if (opt && opt.dataset.satuan) satuanInput.value = opt.dataset.satuan;
    hitungPreviewBaris(tr);
}

function hitungPreviewBaris(tr) {
    const selectBahan = tr.querySelector('select[name*="[item_id]"]');
    const qtyInput = tr.querySelector('.qtyResep');
    const hargaCell = tr.querySelector('.hargaMasterPreview');
    const subtotalCell = tr.querySelector('.subtotalPreview');
    if (!selectBahan || !hargaCell || !subtotalCell) return; // baris linked, skip

    const opt = selectBahan.selectedOptions[0];
    const bahanId = selectBahan.value;
    if (!bahanId || !opt) {
        hargaCell.textContent = '—';
        subtotalCell.textContent = '—';
        return;
    }

    const harga = parseFloat(opt.dataset.harga || '0');
    const satuan = opt.dataset.satuan || '';
    const qty = parseFloat(qtyInput?.value || '0');

    if (!harga) {
        hargaCell.innerHTML = '<span class="text-danger">Rp 0 / ' + satuan + '</span>';
        subtotalCell.textContent = '-';
        return;
    }

    hargaCell.textContent = formatRupiahPreview(harga) + ' / ' + satuan;
    subtotalCell.textContent = qty > 0 ? formatRupiahPreview(qty * harga) : '-';
    hitungTotalHpp();
}

// Total HPP footer (Preview Cepat) -- sum subtotalPreview baris manual saja;
// baris linked (Bumbu Pusat) di-skip (butuh expand server-side, lihat
// hitungKalkulator/Simulasi Produksi Lengkap) & memicu warning terpisah.
function hitungTotalHpp() {
    let total = 0;
    let adaLinked = false;
    document.querySelectorAll('#bodyResep tr').forEach(tr => {
        if (tr.querySelector('.linkedBumbuMarker')) { adaLinked = true; return; }
        const cell = tr.querySelector('.subtotalPreview');
        if (!cell) return;
        const angka = parseFloat((cell.textContent || '').replace(/[^0-9.-]/g, ''));
        if (!isNaN(angka)) total += angka;
    });
    const footer = document.getElementById('totalHppFooter');
    if (footer) footer.textContent = formatRupiahPreview(total);
    const warning = document.getElementById('warningBarisLinked');
    if (warning) warning.hidden = !adaLinked;
}

// ===== Fitur Import dari Bumbu Pusat (2026-09-17) =====
// Baris linked TIDAK pakai dropdown Bahan/Satuan/Mode Harga manual --
// hidden input resep_bumbu_ref_id + qty_per_unit (jumlah porsi bumbu per 1
// unit produk) saja. satuan dipaksa 'porsi' server-side (syncResep()).
function tambahBarisResepLinked(bumbuId, bumbuNama, data) {
    data = data || {};
    const idx = resepIdx++;
    const tr = document.createElement('tr');
    tr.className = 'table-warning';
    tr.innerHTML = `
        <td>
            <span class="badge bg-warning text-dark">🧂 Bumbu Pusat</span>
            <div class="small fw-semibold">${bumbuNama}</div>
            <input type="hidden" name="resep[${idx}][resep_bumbu_ref_id]" value="${bumbuId}">
        </td>
        <td><input type="number" step="0.001" min="0.001" name="resep[${idx}][qty_per_unit]" class="form-control form-control-sm" value="${data.qty_per_unit ?? 1}" title="Jumlah porsi bumbu per 1 unit produk"></td>
        <td class="text-muted small">porsi</td>
        <td class="text-center"><input type="checkbox" name="resep[${idx}][is_wajib]" value="1" ${(data.is_wajib ?? true) ? 'checked' : ''}></td>
        <td class="text-muted small linkedBumbuMarker">— (lihat Simulasi Produksi)</td>
        <td class="text-muted small">— (lihat Simulasi Produksi)</td>
        <td class="text-muted small">Ikut Bumbu Pusat <x-tooltip key="master_produk_jual.baris_linked" /></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusBarisResep(this)"><i class="bi bi-x"></i></button></td>
    `;
    document.getElementById('bodyResep').appendChild(tr);
    hitungTotalHpp();
}

function bukaModalImportBumbu() {
    new bootstrap.Modal(document.getElementById('modalImportBumbu')).show();
    cariBumbuPusat('');
}

let bumbuSearchTimeout = null;
function cariBumbuPusat(search) {
    clearTimeout(bumbuSearchTimeout);
    bumbuSearchTimeout = setTimeout(() => {
        fetch(BUMBU_PUSAT_LIST_URL + '?search=' + encodeURIComponent(search))
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('listBumbuPusat');
                if (!data.data.length) {
                    container.innerHTML = '<div class="text-center text-muted py-3 small">Tidak ada Bumbu Pusat aktif ditemukan.</div>';
                    return;
                }
                container.innerHTML = data.data.map(b => `
                    <button type="button" class="list-group-item list-group-item-action" onclick="pilihBumbuPusat(${b.id}, '${b.nama.replace(/'/g, "\\'")}')">
                        <div class="fw-semibold">${b.nama}</div>
                        <div class="small text-muted">${b.kode} — ${b.jumlah_bahan} bahan</div>
                    </button>
                `).join('');
            })
            .catch(() => {
                document.getElementById('listBumbuPusat').innerHTML = '<div class="text-center text-danger py-3 small">Gagal memuat daftar Bumbu Pusat.</div>';
            });
    }, 250);
}

function pilihBumbuPusat(id, nama) {
    tambahBarisResepLinked(id, nama, { qty_per_unit: 1 });
    bootstrap.Modal.getInstance(document.getElementById('modalImportBumbu')).hide();
}

// Bug fix 2026-09-19: dulu Simulasi Produksi selalu GET dari DB (data
// tersimpan) -- kalau form sedang diedit belum Simpan, hasilnya beda dari
// yang ditampilkan form (lihat CLAUDE.md 4.19). Fix: serialize isi form
// #bodyResep saat ini, kirim via POST, server tetap yang hitung (perlu expand
// Bumbu Pusat server-side).
function serializeResepUntukKalkulator() {
    const rows = [];
    document.querySelectorAll('#bodyResep tr').forEach(tr => {
        const refInput = tr.querySelector('input[name*="[resep_bumbu_ref_id]"]');
        const qtyInput = tr.querySelector('input[name*="[qty_per_unit]"]');
        const wajibInput = tr.querySelector('input[name*="[is_wajib]"]');
        if (refInput) {
            if (!refInput.value) return;
            rows.push({
                resep_bumbu_ref_id: refInput.value,
                qty_per_unit: qtyInput ? qtyInput.value : 0,
                is_wajib: wajibInput && wajibInput.checked ? 1 : 0,
            });
            return;
        }
        const itemSelect = tr.querySelector('select[name*="[item_id]"]');
        if (!itemSelect || !itemSelect.value) return;
        const satuanInput = tr.querySelector('.satuanResep');
        const modeSelect = tr.querySelector('select[name*="[mode_harga]"]');
        rows.push({
            item_id: itemSelect.value,
            qty_per_unit: qtyInput ? qtyInput.value : 0,
            satuan: satuanInput ? satuanInput.value : '',
            is_wajib: wajibInput && wajibInput.checked ? 1 : 0,
            mode_harga: modeSelect ? modeSelect.value : 'gratis',
        });
    });
    return rows;
}

function hitungKalkulator() {
    const jumlah = document.getElementById('jumlahProduksi').value || 1;
    const token = document.querySelector('#formProdukJual input[name="_token"]').value;
    fetch(KALKULATOR_URL + '?jumlah=' + jumlah, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ resep: serializeResepUntukKalkulator() }),
    })
        .then(r => r.json())
        .then(data => {
            let html = `<strong>Total HPP: Rp ${Math.round(data.total_hpp).toLocaleString('id-ID')}</strong><ul class="mb-0 mt-1">`;
            data.breakdown.forEach(b => {
                html += `<li>${b.nama}: ${b.qty} ${b.satuan} (Rp ${Math.round(b.subtotal).toLocaleString('id-ID')})</li>`;
            });
            html += '</ul>';
            document.getElementById('hasilKalkulator').innerHTML = data.breakdown.length ? html : '<em>Belum ada resep di form saat ini.</em>';
        });
}

// ===== Varian =====
let atributIdx = 0;
function tambahBarisAtribut(data) {
    data = data || {};
    const idx = atributIdx++;
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 baris-atribut';
    div.innerHTML = `
        <div class="col-4"><input type="text" name="atribut[${idx}][nama]" class="form-control form-control-sm" placeholder="Nama (cth: Size)" value="${data.nama ?? ''}"></div>
        <div class="col-7"><input type="text" name="atribut[${idx}][nilai]" class="form-control form-control-sm" placeholder="Nilai, pisah koma (cth: S, M, L)" value="${data.nilai ?? ''}"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.baris-atribut').remove()"><i class="bi bi-x"></i></button></div>
    `;
    document.getElementById('bodyAtribut').appendChild(div);
}
ATRIBUT_AWAL.forEach(a => tambahBarisAtribut(a));

function toggleVarianSection() {
    document.getElementById('varianSection').style.display = document.getElementById('punyaVarian').checked ? '' : 'none';
}

function regenerateKombinasi() {
    const rows = document.querySelectorAll('#bodyAtribut .baris-atribut');
    let groups = [];
    rows.forEach(row => {
        const nilaiInput = row.querySelector('input[name*="[nilai]"]').value;
        const values = nilaiInput.split(',').map(v => v.trim()).filter(v => v);
        if (values.length) groups.push(values);
    });

    let combos = [[]];
    groups.forEach(values => {
        const next = [];
        combos.forEach(combo => values.forEach(v => next.push([...combo, v])));
        combos = next;
    });

    const tbody = document.getElementById('bodyKombinasi');
    tbody.innerHTML = '';
    combos.forEach((combo, i) => {
        const sig = [...combo].sort().join('|');
        const existing = VARIANT_AWAL.find(v => v.signature === sig);
        const hargaVal = existing ? (existing.harga_override ?? '') : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${combo.join(' / ')}</td>
            <td><input type="number" name="harga_override[${i}]" class="form-control form-control-sm" placeholder="default" value="${hargaVal}"></td>
        `;
        tbody.appendChild(tr);
    });
}
if (document.getElementById('punyaVarian').checked) regenerateKombinasi();

function previewFoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('fotoPreview').src = e.target.result;
        document.getElementById('fotoPreview').style.display = '';
        document.getElementById('fotoPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endpush
