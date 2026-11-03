{{--
    4.44 Poin 2 — overlay cetak Label Produk (stiker), porting erp-coffeeshop
    cup-label-overlay (sudah termasuk perbaikan 0dea130 & 585ecb0). Dipasang sekali
    di layouts/app. Dipanggil lewat JS: await window.openCupLabel(url)
    (url = route label-cup.order, respons JSON). Kalau tidak ada label
    (kategori tidak dicentang / fitur nonaktif di cabang) overlay tidak muncul.

    Overlay sengaja bukan Bootstrap modal supaya bisa tampil di atas modal
    struk tanpa masalah tumpukan modal. Printer label Bluetooth disimpan
    terpisah dari printer struk (localStorage 'btLastLabelPrinterName'),
    jadi tablet bisa memakai 2 printer berbeda.
--}}
<div id="cupLabelOverlay" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:16px">
    <div class="card shadow" style="width:100%;max-width:440px;max-height:90vh;display:flex;flex-direction:column">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-tag me-1"></i>Label Produk — <span id="cupLabelCount">0</span> stiker</span>
            <x-panduan-button slug="label-produk" label="" />
        </div>
        <div class="card-body" style="overflow:auto">
            <div id="cupLabelPreview" class="d-flex flex-column gap-2 mb-3"></div>

            {{-- Aplikasi kasir Android: cetak langsung ke printer label yang diatur di aplikasi --}}
            <div class="d-grid gap-2" id="cupLabelApkControls" style="display:none">
                <button type="button" class="btn btn-primary btn-lg" id="cupLabelBtnApk" onclick="window.cupLabelCetakApk()">🖨️ Cetak Label</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.KDApk && window.KDApk.bukaPengaturan()">⚙️ Pengaturan Printer Aplikasi</button>
            </div>

            <div class="d-grid gap-2" id="cupLabelWebControls">
                <button type="button" class="btn btn-primary" onclick="window.cupLabelCetakBrowser()">🖨️ Cetak Browser</button>
                <button type="button" class="btn btn-outline-primary" id="cupLabelBtnLast" style="display:none" onclick="window.cupLabelCetakBluetooth(true)"></button>
                <div class="input-group">
                    <select id="cupLabelProtocol" class="form-select" title="Bahasa printer label Bluetooth">
                        <option value="escpos">Printer ESC/POS (mis. Blueprint ECO80BT)</option>
                        <option value="tspl">Printer label (TSPL)</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.cupLabelCetakBluetooth(false)">📶 Pilih Printer Bluetooth</button>
                </div>
            </div>
            <div class="small mt-2" id="cupLabelStatus" style="white-space:pre-line"></div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-danger" id="cupLabelBtnForget" style="display:none" onclick="window.cupLabelLupakanPrinter()">Lupakan printer label</button>
            <button type="button" class="btn btn-success ms-auto" onclick="window.cupLabelTutup()">Selesai</button>
        </div>
    </div>
</div>

<script>
(function () {
    var KEY_PRINTER  = 'btLastLabelPrinterName';
    var KEY_PROTOCOL = 'labelCupProtocol';
    var BT_SERVICE_UUIDS = [
        '0000ae30-0000-1000-8000-00805f9b34fb', '0000ae3a-0000-1000-8000-00805f9b34fb',
        '000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        '0000ff00-0000-1000-8000-00805f9b34fb', '0000fee7-0000-1000-8000-00805f9b34fb',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2', '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
    ];
    var state = null, resolver = null;

    function el(id) { return document.getElementById(id); }
    function ls(key, val) {
        try { if (val === undefined) return localStorage.getItem(key); if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, val); } catch (e) { return null; }
    }
    function status(msg, cls) { var s = el('cupLabelStatus'); s.textContent = msg || ''; s.className = 'small mt-2 text-' + (cls || 'muted'); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    // Printer thermal tidak paham UTF-8: buang aksen & simbol non-ASCII
    function ascii(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/·/g, '-').replace(/[^\x20-\x7E]/g, ''); }

    function refreshPrinterUi() {
        var saved = ls(KEY_PRINTER);
        var last = el('cupLabelBtnLast');
        last.style.display = saved ? '' : 'none';
        last.textContent = saved ? '🔁 Cetak ke "' + saved + '"' : '';
        el('cupLabelBtnForget').style.display = saved ? '' : 'none';
    }

    function renderPreview(labels) {
        el('cupLabelCount').textContent = labels.length;
        el('cupLabelPreview').innerHTML = labels.map(function (l) {
            return '<div class="border rounded p-2 small" style="font-family:Arial,sans-serif">'
                + '<div class="d-flex justify-content-between fw-bold border-bottom pb-1"><span>' + esc(l.toko.toUpperCase()) + '</span><span>#' + esc(l.nomor) + ' · ' + l.ke + '/' + l.dari + '</span></div>'
                + '<div class="fw-bold mt-1">' + esc(l.nama) + '</div>'
                + (l.opsi && l.opsi.length ? '<div>' + esc(l.opsi.join(' · ')) + '</div>' : '')
                + (l.catatan ? '<div class="fst-italic">' + esc(l.catatan) + '</div>' : '')
                + (l.pelanggan ? '<div>a.n. ' + esc(l.pelanggan) + '</div>' : '')
                + (l.exp ? '<div class="fw-bold">Exp: ' + esc(l.exp) + '</div>' : '')
                + '<div class="text-muted">' + esc(l.info) + '</div></div>';
        }).join('');
    }

    // ---- Buka overlay. Resolve true kalau overlay tampil, false kalau tidak ada label ----
    // opsi.otomatis = dipanggil otomatis (setelah struk) — di aplikasi kasir dengan "cetak label otomatis" aktif, label
    // langsung dicetak tanpa membuka jendela.
    window.openCupLabel = async function (url, opsi) {
        opsi = opsi || {};
        try {
            var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return false;
            var data = await res.json();
            if (!data.aktif || !data.labels || !data.labels.length) return false;
            state = data;
        } catch (e) { return false; }

        var apk = window.KDApk && window.KDApk.ada();
        if (apk && opsi.otomatis && window.KDApk.info().auto_label) {
            await cetakApk(true);
            return true;
        }

        renderPreview(state.labels);
        el('cupLabelApkControls').style.display = apk ? '' : 'none';
        el('cupLabelWebControls').style.display = apk ? 'none' : '';
        if (!apk) {
            el('cupLabelProtocol').value = ls(KEY_PROTOCOL) || 'escpos';
            refreshPrinterUi();
        } else {
            el('cupLabelBtnForget').style.display = 'none';
        }
        status('');
        el('cupLabelOverlay').style.display = 'flex';
        return new Promise(function (resolve) { resolver = resolve; });
    };

    // ---- Aplikasi kasir: kirim ke printer label lewat jembatan Android ----
    async function cetakApk(diam) {
        if (!state) return;
        var proto = (window.KDApk.info().label_protokol === 'escpos') ? 'escpos' : 'tspl';
        var btn = el('cupLabelBtnApk');
        if (btn) btn.disabled = true;
        if (!diam) status('Mengirim ke printer label…', 'muted');
        try {
            var bytes = proto === 'tspl' ? buildTspl() : buildEscpos();
            await window.KDApk.cetak('label', bytes);
            var ok = '✓ ' + state.labels.length + ' label tercetak.';
            diam ? window.KDApk.toast(ok, true) : status(ok, 'success');
        } catch (e) {
            diam ? window.KDApk.toast('Label gagal: ' + e.message, false) : status('Gagal cetak label: ' + e.message, 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    }
    window.cupLabelCetakApk = function () { return cetakApk(false); };

    window.cupLabelTutup = function () {
        el('cupLabelOverlay').style.display = 'none';
        if (resolver) { var r = resolver; resolver = null; r(true); }
    };

    window.cupLabelCetakBrowser = function () {
        if (!state) return;
        var u = state.print_url + (state.print_url.indexOf('?') >= 0 ? '&' : '?') + 'autoprint=1';
        var w = window.open(u, '_blank');
        if (!w) status('Popup diblokir browser. Izinkan popup untuk situs ini lalu coba lagi.', 'danger');
        else status('Halaman label dibuka di tab baru — pilih printer label di dialog print.', 'success');
    };

    window.cupLabelLupakanPrinter = function () { ls(KEY_PRINTER, null); refreshPrinterUi(); status('Printer label dihapus.', 'muted'); };

    // ---- Builder data printer ----
    function wrap(text, n) {
        var words = ascii(text).split(/\s+/), lines = [], cur = '';
        words.forEach(function (w) {
            if ((cur + ' ' + w).trim().length > n) { if (cur) lines.push(cur); cur = w.slice(0, n); }
            else cur = (cur + ' ' + w).trim();
        });
        if (cur) lines.push(cur);
        return lines;
    }
    function bodyLines(l) {
        var out = [];
        if (l.opsi && l.opsi.length) out.push(l.opsi.join(' - '));
        if (l.catatan) out.push(l.catatan);
        if (l.pelanggan) out.push('a.n. ' + l.pelanggan);
        if (l.exp) out.push('Exp: ' + l.exp);
        return out;
    }

    function buildTspl() {
        var w = state.ukuran.lebar, h = state.ukuran.tinggi, dotsW = w * 8, dotsH = h * 8;
        var out = '';
        state.labels.forEach(function (l) {
            var cmds = ['SIZE ' + w + ' mm,' + h + ' mm', 'GAP 2 mm,0 mm', 'DIRECTION 1', 'CLS'];
            var y = 10, x = 12;
            var colS = Math.floor((dotsW - 24) / 12), colB = Math.floor((dotsW - 24) / 16);
            var kanan = '#' + l.nomor + ' ' + l.ke + '/' + l.dari;
            var q = function (t) { return '"' + ascii(t).replace(/"/g, "'") + '"'; };
            // Nama toko (Pengaturan Umum) pakai font kecil 8 dot supaya muat utuh
            // di stiker 40 mm; nomor antrean font 12 dot rata kanan.
            var xKanan = dotsW - 12 - kanan.length * 12;
            var head = ascii(l.toko.toUpperCase()).slice(0, Math.max(0, Math.floor((xKanan - x - 8) / 8)));
            cmds.push('TEXT ' + x + ',' + (y + 5) + ',"1",0,1,1,' + q(head));
            cmds.push('TEXT ' + xKanan + ',' + y + ',"2",0,1,1,' + q(kanan));
            y += 24; cmds.push('BAR ' + x + ',' + y + ',' + (dotsW - 24) + ',2'); y += 6;
            wrap(l.nama, colB).slice(0, 2).forEach(function (t) { if (y + 26 < dotsH - 20) { cmds.push('TEXT ' + x + ',' + y + ',"3",0,1,1,' + q(t)); y += 28; } });
            bodyLines(l).forEach(function (b) {
                wrap(b, colS).forEach(function (t) { if (y + 22 < dotsH - 20) { cmds.push('TEXT ' + x + ',' + y + ',"2",0,1,1,' + q(t)); y += 22; } });
            });
            cmds.push('TEXT ' + x + ',' + (dotsH - 18) + ',"1",0,1,1,' + q(l.info));
            cmds.push('PRINT 1,1');
            out += cmds.join('\r\n') + '\r\n';
        });
        return new TextEncoder().encode(out);
    }

    // Printer ESC/POS (printer struk/label yang tidak mengerti TSPL, mis. Blueprint
    // ECO80BT). Lebar baris mengikuti lebar stiker (font A = 12 dot ≈ 1,5 mm),
    // tinggi tiap stiker dihitung dalam dot lalu sisa dimajukan dengan ESC J
    // supaya stiker berikutnya mulai di posisi yang sama (tinggi + celah 2 mm).
    function buildEscpos() {
        var ESC = 0x1B, bytes = [];
        var SP = 24;                                             // jarak baris = tinggi huruf font A
        var cols = Math.max(16, Math.floor((state.ukuran.lebar - 2) * 8 / 12));
        var pitch = Math.round((state.ukuran.tinggi + 2) * 8);   // dot per stiker (+ celah 2 mm)
        var maksBaris = Math.max(4, Math.floor((pitch - 32) / SP));
        var enc = new TextEncoder();
        var push = function (arr) { for (var i = 0; i < arr.length; i++) bytes.push(arr[i]); };

        state.labels.forEach(function (l) {
            var baris = 0;
            var line = function (t) { if (baris < maksBaris) { push(enc.encode(ascii(t).slice(0, cols) + '\n')); baris++; } };

            push([ESC, 0x40, ESC, 0x61, 0x00, ESC, 0x33, SP]);   // reset, rata kiri, jarak baris
            line(l.toko.toUpperCase());
            var kanan = l.ke + '/' + l.dari, kiri = '#' + l.nomor;
            push([ESC, 0x45, 0x01]);
            line(kiri + ' '.repeat(Math.max(1, cols - kiri.length - kanan.length)) + kanan);
            wrap(l.nama, cols).slice(0, 2).forEach(line);
            push([ESC, 0x45, 0x00]);
            // Sisakan 1 baris terakhir untuk tipe pesanan + jam
            var badan = [];
            bodyLines(l).forEach(function (b) { badan = badan.concat(wrap(b, cols)); });
            badan.slice(0, Math.max(0, maksBaris - baris - 1)).forEach(line);
            line(l.info);

            var sisa = pitch - baris * SP;                       // maju tepat ke stiker berikutnya
            while (sisa > 0) { var n = Math.min(255, sisa); push([ESC, 0x4A, n]); sisa -= n; }
        });
        return new Uint8Array(bytes);
    }

    // ---- Bluetooth (pola sama dengan cetak struk, printer disimpan terpisah) ----
    async function findChar(server) {
        for (var i = 0; i < BT_SERVICE_UUIDS.length; i++) {
            try {
                var svc = await server.getPrimaryService(BT_SERVICE_UUIDS[i]);
                var chars = await svc.getCharacteristics();
                for (var j = 0; j < chars.length; j++) {
                    if (chars[j].properties.writeWithoutResponse || chars[j].properties.write) return chars[j];
                }
            } catch (e) { /* lanjut */ }
        }
        return null;
    }
    async function send(chr, data) {
        for (var off = 0; off < data.length; off += 180) {
            var chunk = data.slice(off, off + 180);
            if (chr.properties.writeWithoutResponse) await chr.writeValueWithoutResponse(chunk); else await chr.writeValue(chunk);
            await new Promise(function (r) { setTimeout(r, 30); });
        }
    }

    window.cupLabelCetakBluetooth = async function (pakaiTersimpan) {
        if (!state) return;
        var proto = el('cupLabelProtocol').value;
        ls(KEY_PROTOCOL, proto);
        var secure = location.protocol === 'https:' || ['localhost', '127.0.0.1'].indexOf(location.hostname) >= 0;
        if (!secure || !navigator.bluetooth) {
            status('Cetak Bluetooth butuh Chrome/Edge dan HTTPS. Gunakan "Cetak Browser".', 'danger');
            return;
        }
        var device = null, server = null, saved = ls(KEY_PRINTER);
        try {
            if (pakaiTersimpan && saved && navigator.bluetooth.getDevices) {
                var list = await navigator.bluetooth.getDevices();
                device = list.find(function (d) { return d.name === saved; }) || null;
            }
            if (!device) {
                status('Pilih printer label dari daftar...', 'muted');
                device = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BT_SERVICE_UUIDS });
            }
            status('Menghubungkan ke "' + (device.name || 'Printer') + '"...', 'muted');
            server = await device.gatt.connect();
            var chr = await findChar(server);
            if (!chr) throw new Error('Service printer tidak ditemukan.');
            await send(chr, proto === 'tspl' ? buildTspl() : buildEscpos());
            ls(KEY_PRINTER, device.name || 'Printer');
            refreshPrinterUi();
            status('✓ ' + state.labels.length + ' label terkirim ke "' + (device.name || 'Printer') + '".', 'success');
        } catch (e) {
            status(e && e.name === 'NotFoundError' ? 'Pemilihan printer dibatalkan.' : 'Gagal cetak Bluetooth: ' + (e && e.message ? e.message : e), 'danger');
        } finally {
            await new Promise(function (r) { setTimeout(r, 200); });
            if (server && server.connected) { try { server.disconnect(); } catch (e) {} }
        }
    };
})();
</script>
