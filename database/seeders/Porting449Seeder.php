<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * 4.49 — porting dari erp-coffeeshop (CLAUDE.md coffeeshop 4.50/4.51/12.15):
 * Label Produk gambar (Tahap D), POS HP (Tahap C), Produksi (B1),
 * Kirim ke Cabang (B2), Retur Cabang (B3).
 *
 * ADDITIVE & idempotent — aman di production: panduan memakai penanda
 * (`<!-- ... -->`) supaya tidak dobel, tooltip updateOrCreate per key.
 * TIDAK menyentuh centangan role yang sudah ada (izin baru ditangani per tahap,
 * hanya dicentang saat izin itu pertama kali dibuat).
 * Jalankan SETELAH LabelProdukSeeder (yang menulis ulang panduan label-produk).
 */
class Porting449Seeder extends Seeder
{
    public function run(): void
    {
        $this->labelGambar();

        $this->command?->info('✅ Porting 4.49: panduan & tooltip (idempotent).');
    }

    // ===================== Tahap D — Label Produk gambar =====================

    private function labelGambar(): void
    {
        $p = Panduan::where('slug', 'label-produk')->first();
        if ($p) {
            $konten = (string) $p->konten;
            // Kalimat lama: bahasa bawaan ESC/POS. Coffeeshop membuktikan Blueprint ECO80BT mencetak TSPL.
            $konten = str_replace(
                '- Di Chrome/PC pilih bahasa printer: **ESC/POS** (mis. Blueprint ECO80BT) atau **TSPL** (printer label khusus). Kalau hasil cetak berupa huruf acak, ganti pilihannya.',
                self::KALIMAT_BAHASA_PRINTER,
                $konten
            );
            if (! str_contains($konten, '<!-- LABEL-GAMBAR-449 -->')) {
                $konten = rtrim($konten) . "\n\n" . self::BAGIAN_LABEL_GAMBAR;
            }
            if ($konten !== (string) $p->konten) {
                $p->update(['konten' => $konten]);
            }
        }

        foreach ([
            ['key' => 'label_cup.posisi', 'title' => 'Atur posisi label', 'modul' => 'penjualan',
                'content' => 'Kalau tulisan terpotong atau tidak pas di stiker: cetak label uji, lalu geser kiri/kanan atau atas/bawah dalam mm sampai bingkainya pas di tepi stiker. Angka di sini ditambahkan ke geser cabang dan hanya berlaku di perangkat ini.'],
            ['key' => 'cabang.label_cup_geser', 'title' => 'Geser posisi label', 'modul' => 'cabang',
                'content' => 'Printer & gulungan stiker bisa menggeser isi label beberapa mm. Kiri terlalu lebar dan kanan terpotong → isi minus (mis. -3,5). Kanan terlalu lebar → plus. Berlaku untuk semua tablet/HP cabang ini.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => $t['modul'],
                'aktif' => true, 'urutan' => 70 + $i,
            ]);
        }
    }

    public const KALIMAT_BAHASA_PRINTER = '- Di Chrome/PC pilih bahasa printer: **TSPL** (printer label, mis. Blueprint ECO80BT — disarankan) atau **ESC/POS** (printer struk). Kalau hasil cetak berupa huruf acak, ganti pilihannya.';

    public const BAGIAN_LABEL_GAMBAR = <<<'MARKDOWN'
<!-- LABEL-GAMBAR-449 -->
## Cara cetak: Gambar atau Teks printer
Di jendela Label Produk buka **⚙️ Atur posisi label (perangkat ini)** → **Cara cetak**:
- **Gambar** (bawaan, disarankan) — label digambar oleh aplikasi lalu dikirim ke printer sebagai gambar, jadi ukuran huruf & posisi pasti sama di semua printer, dan **contoh di layar = hasil cetak**. Isinya: nama usaha di atas, nomor pesanan besar + urutan stiker (1/3) di kanan, nama produk tebal, varian & catatan, **Exp** (Frozen), nama pelanggan, tipe pesanan + jam + tanggal di bawah.
- **Teks printer** — memakai huruf bawaan printer (ukuran huruf tergantung merek printer). Pakai kalau printer tidak bisa mencetak gambar.
- Kalau dengan cara Gambar stiker tercetak **hitam semua**, centang **Balik hitam-putih**.
- Cara Gambar dan Label Uji hanya untuk printer label **TSPL**. Printer struk (ESC/POS) tetap mencetak teks.

## Label terpotong / tidak pas di stiker
Setiap printer & gulungan stiker posisinya sedikit berbeda.

**Cara paling mudah (sekali untuk semua tablet/HP cabang):** buka **Cabang → Edit → Label Produk**, isi **Geser kiri/kanan (mm)** — kiri terlalu lebar & kanan terpotong → angka minus (mis. **−3,5**), kanan terlalu lebar → plus. **Geser atas/bawah**: plus = ke bawah. Simpan, lalu cetak lagi.

Kalau satu perangkat masih sedikit meleset, di jendela Label Produk buka **⚙️ Atur posisi label (perangkat ini)** — angkanya **ditambahkan** ke setelan cabang:
1. Klik **🧪 Cetak Label Uji** — tercetak bingkai tebal yang seharusnya pas di tepi stiker.
2. Terpotong di **kiri** → isi geser kiri/kanan dengan angka plus; terpotong di **kanan** → angka minus.
3. Tulisan terbalik → centang/hapus centang **Putar tulisan 180°**.
4. Cetak uji lagi sampai pas. Pengaturan ini disimpan di perangkat itu saja.

Pastikan juga **ukuran stiker** di Cabang → Edit sama dengan stiker yang terpasang (mis. 40 × 30 mm).

**Catatan pesanan** (kolom Catatan di POS) ikut tercetak di setiap stiker pesanan itu, di bawah catatan item.
MARKDOWN;
}
