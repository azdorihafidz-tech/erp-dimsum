<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;

/**
 * 4.51 — Perbaikan temuan A–G audit Kamus Menu: bagian panduan & tooltip yang berubah.
 * Idempotent: bagian panduan ditempel sekali (marker), tooltip updateOrCreate.
 */
class Perbaikan451Seeder extends Seeder
{
    public const MARKER = '<!-- PERBAIKAN-451 -->';

    public function run(): void
    {
        foreach ($this->sections() as $slug => $konten) {
            $panduan = Panduan::where('slug', $slug)->first();
            if (! $panduan || str_contains((string) $panduan->konten, self::MARKER)) {
                continue;
            }
            $panduan->konten = rtrim((string) $panduan->konten) . "\n\n" . self::MARKER . "\n" . trim($konten) . "\n";
            $panduan->save();
        }

        foreach ([
            ['penggajian.bayar_kas', 'Dibayar dari kas', 'Kas yang uangnya dipakai membayar gaji. Saldo kas ini berkurang sebesar total gaji dan tercatat di Kas & Transaksi (kategori Gaji Karyawan). Pilihan: kas cabang karyawan atau kas Gudang Pusat. Salah pilih? Klik Batal Bayar lalu bayar ulang.', 'penggajian'],
            ['recurring.frekuensi', 'Frekuensi', 'Harian = setiap hari; Mingguan = setiap hari yang sama dengan Tanggal Mulai; Bulanan = setiap tanggal jatuh tempo (29–31 → hari terakhir bulan pendek); Tahunan = tanggal jatuh tempo di bulan Tanggal Mulai. Dibuat 1 kali per periode — tidak akan dobel.', 'recurring'],
            ['recurring.tanggal_jatuh_tempo', 'Tanggal Jatuh Tempo', 'Untuk frekuensi Bulanan & Tahunan. Kalau hari itu tidak ada yang membuka aplikasi, transaksi tetap dibuat saat aplikasi dibuka berikutnya (bertanggal jatuh tempo).', 'recurring'],
        ] as $i => [$key, $title, $content, $modul]) {
            Tooltip::updateOrCreate(['key' => $key], [
                'title' => $title, 'content' => $content, 'modul' => $modul, 'aktif' => true, 'urutan' => 90 + $i,
            ]);
        }
    }

    private function sections(): array
    {
        return [
            'penggajian' => <<<'MD'
## 🔄 Update 2026-11-16 — Bayar gaji tercatat di kas, potongan telat, slip terkunci
- Tombol **Bayar** sekarang meminta **kas** yang dipakai membayar (kas cabang karyawan atau kas Gudang Pusat) dan tanggal bayar. Saldo kas itu **berkurang** sebesar total gaji dan otomatis tercatat di Kas & Transaksi (kategori Gaji Karyawan) — **tidak perlu dicatat manual lagi**.
- Salah bayar? Klik **Batal Bayar**: uang kembali ke kas, slip kembali Disetujui/Draft.
- Slip yang sudah **Disetujui/Dibayar** tidak ikut dihitung ulang saat Generate (dilewati dan disebutkan namanya). Dulu nominalnya tertimpa dan statusnya kembali Draft.
- **Potongan telat** = total menit telat (dari Scan Absensi) × "Potongan telat per menit" di Pengaturan Penggajian. Tampil di slip sebagai baris tersendiri.
- Slip lama yang ditandai Dibayar sebelum pembaruan ini tidak tercatat di kas — pastikan sudah dicatat manual di Kas & Transaksi.
MD,
            'recurring' => <<<'MD'
## 🔄 Update 2026-11-16 — Frekuensi berfungsi & tidak dobel
- Frekuensi **Harian, Mingguan, Bulanan, Tahunan** sekarang benar-benar dipakai (dulu semua berjalan bulanan).
- Setiap template dibuat **1 kali per periode** — dari jadwal otomatis, saat aplikasi dibuka, maupun tombol **Generate** (tombol menolak kalau periode itu sudah dibuat).
- Tanggal 29–31 dipakai sebagai hari terakhir di bulan pendek. Jatuh tempo yang terlewat (tidak ada yang membuka aplikasi) dibuat saat aplikasi dibuka berikutnya, bertanggal jatuh temponya.
- Pilihan "Auto-approve" dihapus karena tidak berfungsi.
MD,
            'cabang' => <<<'MD'
## 🔄 Update 2026-11-16 — Hapus cabang
- Cabang yang sudah punya order, PO, transaksi kas, pergerakan stok, absensi, atau slip gaji **tidak bisa dihapus** (dulu semua data itu ikut tersembunyi dan hilang dari laporan). Pakai **Nonaktifkan**. Hapus hanya untuk cabang yang salah dibuat dan masih kosong.
MD,
            'laporan-keuangan' => <<<'MD'
## 🔄 Update 2026-11-16 — Transfer internal tidak dihitung
- **Laba Rugi** di laporan ini tidak lagi menghitung transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas (uang yang hanya pindah tempat). Jumlahnya ditampilkan sebagai keterangan di bawah kotak ringkasan.
- **Arus Kas** tetap menghitung semua uang yang bergerak, tetapi bagian transfer internal & saldo awal ditampilkan terpisah.
- User yang hanya boleh melihat cabangnya sendiri selalu melihat cabangnya (tidak bisa membuka cabang lain lewat alamat).
MD,
            'keuangan-laporan' => <<<'MD'
## 🔄 Update 2026-11-16 — Pemasukan & Pengeluaran Usaha
- Halaman ini sekarang berjudul **Pemasukan & Pengeluaran Usaha** dan tidak menghitung transfer antar kas, setoran kasir, transfer dana, serta saldo awal kas (ditampilkan sebagai keterangan). Angkanya berbasis kas — untuk laba dengan modal barang pakai Laba Rugi Formal atau Closing.
MD,
            'laporan-cabang' => <<<'MD'
## 🔄 Update 2026-11-16 — Pemasukan/pengeluaran usaha
- Kolom pemasukan & pengeluaran per cabang tidak lagi ikut menghitung transfer antar kas, setoran kasir, transfer dana, dan saldo awal kas. Setoran tetap tampil di kolom setoran.
MD,
            'laporan-neraca' => <<<'MD'
## 🔄 Update 2026-11-16 — Persediaan barang yang dihapus
- Persediaan tidak lagi menghitung sisa stok milik barang yang sudah dihapus (sama dengan Dashboard & Laporan Stok). Kalau selisih neraca berubah setelah pembaruan ini, sesuaikan Modal Owner.
MD,
        ];
    }
}
