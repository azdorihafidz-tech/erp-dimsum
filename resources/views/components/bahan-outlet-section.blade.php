{{--
    Tab "Pengaturan per Outlet" di form Bahan Baku & Kemasan (CLAUDE.md 4.38-E).
    Per lokasi (outlet + Gudang Pusat):
    - Harga beli per satuan pakai (item_cabang.harga_beli) ↔ per unit beli
      (mis. per pack) — saling terhitung otomatis, yang disimpan per satuan pakai.
    - Qty Minimum (stocks.qty_minimum) — dipakai alert & Laporan Stok Minimum.
    - Stok awal (khusus form tambah).
    Variabel: $item (Item|null), $cabangList (Collection<Cabang>), $stokLokasi
    (Collection<Stock> keyBy lokasi_id, khusus edit).
--}}
@props(['item' => null, 'cabangList' => collect(), 'stokLokasi' => collect()])
@php
    $hargaCabang = $item ? $item->itemCabang->whereNotNull('harga_beli')->pluck('harga_beli', 'cabang_id') : collect();
    $satuanAwal = old('satuan', $item?->satuan) ?: 'satuan';
    $minAwal = (float) old('qty_minimum', $item?->qty_minimum ?? 0);
    $fmtAngka = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp

<div class="card mb-3" id="bahanOutletSection">
    <div class="card-header fw-semibold">
        Harga Beli &amp; Qty Minimum per Outlet <x-tooltip key="master_bahan_baku.pengaturan_outlet" />
    </div>
    <div class="card-body">
        <div class="alert alert-info small py-2">
            <i class="bi bi-info-circle me-1"></i>
            Kosongkan harga = memakai <strong>Harga Standar</strong> (tab Data Barang).
            Harga outlet dipakai sebagai harga awal saat outlet itu membuat PO, dan untuk perkiraan HPP produk di outlet itu.
            <span class="wrap-info-pack d-none">Isi salah satu kolom harga — kolom lain terhitung otomatis.</span>
        </div>
        @if($cabangList->isEmpty())
            <div class="text-muted small">Belum ada outlet aktif.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-1">
                <thead class="table-light">
                    <tr>
                        <th>Outlet</th>
                        <th style="min-width:150px">Harga per <span class="label-satuan-pakai">{{ $satuanAwal }}</span></th>
                        <th class="kolom-pack d-none" style="min-width:150px">Harga per <span class="label-unit-beli">pack</span></th>
                        <th style="min-width:120px">Qty Minimum</th>
                        @if($item)
                            <th class="text-end">Stok Sekarang</th>
                        @else
                            <th style="min-width:110px">Stok Awal <x-tooltip key="master_bahan_baku.stok_awal" /></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($cabangList as $c)
                    @php $stok = $stokLokasi->get($c->id); @endphp
                    <tr>
                        <td class="small">
                            {{ $c->nama_cabang }}
                            @if($c->tipe === 'gudang_pusat')<span class="badge bg-secondary-subtle text-secondary-emphasis">Gudang</span>@endif
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted">Rp</span>
                                <input type="number" step="0.01" min="0" class="form-control input-harga-pcs @error('harga_cabang.' . $c->id) is-invalid @enderror"
                                    name="harga_cabang[{{ $c->id }}]"
                                    value="{{ old('harga_cabang.' . $c->id, isset($hargaCabang[$c->id]) ? $fmtAngka($hargaCabang[$c->id]) : '') }}"
                                    placeholder="Harga Standar">
                            </div>
                        </td>
                        <td class="kolom-pack d-none">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted">Rp</span>
                                <input type="number" step="1" min="0" class="form-control input-harga-pack" placeholder="—" autocomplete="off">
                            </div>
                        </td>
                        <td>
                            <input type="number" step="0.001" min="0" class="form-control form-control-sm @error('minimum_cabang.' . $c->id) is-invalid @enderror"
                                name="minimum_cabang[{{ $c->id }}]"
                                value="{{ old('minimum_cabang.' . $c->id, $stok ? $fmtAngka($stok->qty_minimum) : '') }}"
                                placeholder="nilai awal {{ $fmtAngka($minAwal) ?: '0' }}">
                        </td>
                        @if($item)
                            <td class="text-end small">{{ $stok ? fmt_qty($stok->qty) : '—' }} <span class="text-muted">{{ $item->satuan }}</span></td>
                        @else
                            <td>
                                <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                    name="stok_awal[{{ $c->id }}]" value="{{ old('stok_awal.' . $c->id) }}" placeholder="0">
                            </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-text">
            Qty Minimum kosong = memakai <strong>nilai awal</strong> (Qty Minimum di tab Data Barang) saat stok outlet itu pertama kali tercatat.
            Qty Minimum juga bisa diubah per baris di menu <a href="{{ route('stok.index') }}">Stok &amp; Inventori</a>.
        </div>
        @endif
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    const sec = document.getElementById('bahanOutletSection');
    if (!sec) return;
    const satuanEl = document.querySelector('[name="satuan"]');
    const unitEl = document.querySelector('[name="unit_beli"]');
    const isiEl = document.querySelector('[name="isi_per_unit_beli"]');
    const isi = () => parseFloat(isiEl?.value) || 0;
    const unit = () => (unitEl?.value || '').trim();
    const bulat2 = n => Math.round(n * 100) / 100;

    function isiPackDariPcs(tr) {
        const pcs = tr.querySelector('.input-harga-pcs');
        const pack = tr.querySelector('.input-harga-pack');
        if (!pack || document.activeElement === pack) return;
        const v = parseFloat(pcs.value);
        pack.value = (isi() > 0 && !isNaN(v) && pcs.value !== '') ? Math.round(v * isi()) : '';
    }

    function refresh() {
        const adaPack = unit() && isi() > 0;
        sec.querySelectorAll('.kolom-pack').forEach(el => el.classList.toggle('d-none', !adaPack));
        sec.querySelector('.wrap-info-pack')?.classList.toggle('d-none', !adaPack);
        sec.querySelectorAll('.label-satuan-pakai').forEach(el => el.textContent = (satuanEl?.value || 'satuan').trim());
        sec.querySelectorAll('.label-unit-beli').forEach(el => el.textContent = unit() || 'pack');
        sec.querySelectorAll('tbody tr').forEach(isiPackDariPcs);
    }

    sec.addEventListener('input', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('input-harga-pack')) {
            const v = parseFloat(e.target.value);
            tr.querySelector('.input-harga-pcs').value = (isi() > 0 && !isNaN(v) && e.target.value !== '') ? bulat2(v / isi()) : '';
        } else if (e.target.classList.contains('input-harga-pcs')) {
            isiPackDariPcs(tr);
        }
    });
    [satuanEl, unitEl, isiEl].forEach(el => { el?.addEventListener('input', refresh); el?.addEventListener('change', refresh); });
    refresh();
    // Ada error validasi di tab ini → buka tabnya supaya terlihat
    const tabBtn = document.getElementById('tab-pengaturan-outlet');
    if (tabBtn && sec.querySelector('.is-invalid') && window.bootstrap) {
        window.bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    }
})();
</script>
@endpush
@endonce
