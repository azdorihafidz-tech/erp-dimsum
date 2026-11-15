<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Laporan Kerugian Stok (Excel) — data dari LaporanKerugianStokService::hitung(). */
class LaporanKerugianStokExport implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    private array $judulBaris = [];

    public function __construct(private array $d, private string $lokasi, private ?string $namaUser = null) {}

    public function title(): string
    {
        return 'Kerugian Stok';
    }

    private function judul(array &$out, string $teks, array $kolom): void
    {
        $out[] = [];
        $out[] = [$teks];
        $out[] = $kolom;
        $this->judulBaris[] = count($out);
    }

    public function array(): array
    {
        $d = $this->d;
        $out = [
            ['LAPORAN KERUGIAN STOK'],
            ['Periode', $d['dari']->format('d/m/Y') . ' – ' . $d['sampai']->format('d/m/Y')],
            ['Lokasi', $this->lokasi],
            [],
            ['Kerugian kotor', $d['kerugian_kotor']],
            ['Dipulihkan (uang)', $d['dipulihkan_uang']],
            ['Kerugian dibatalkan (barang kembali)', $d['dibatalkan']],
            ['Kelebihan stok', $d['nilai_lebih']],
            ['Kerugian bersih', $d['kerugian_bersih']],
        ];
        $this->judul($out, 'PER PENYEBAB', ['Penyebab', 'Jumlah barang', 'Kejadian', 'Nilai']);
        foreach ($d['per_penyebab'] as $p) {
            $out[] = [$p->label, (int) $p->barang, (int) $p->kejadian, $p->nilai];
        }
        $this->judul($out, 'PER LOKASI', ['Lokasi', 'Opname disetujui', 'Akurasi rata-rata (%)', 'Nilai kurang', 'Nilai lebih']);
        foreach ($d['per_lokasi'] as $l) {
            $out[] = [$l->nama, $l->opname, $l->akurasi, $l->kurang, $l->lebih];
        }
        $this->judul($out, 'PER BARANG (kerugian terbesar)', ['Barang', 'Kode', 'Lokasi', 'Jumlah', 'Satuan', 'Nilai', 'Penyebab', 'Kali di opname']);
        foreach ($d['per_barang'] as $b) {
            $out[] = [$b->nama_item, $b->kode_item, $b->nama_cabang, (float) $b->qty, $b->satuan, (float) $b->nilai, $b->penyebab, (int) $b->opname];
        }
        $this->judul($out, 'STOK RUSAK TERTAHAN (saat ini)', ['Lokasi', 'Baris', 'Nilai', 'Umur tertua (hari)']);
        foreach ($d['stok_rusak'] as $s) {
            $out[] = [$s->nama_cabang, (int) $s->baris, $s->nilai, $s->umur];
        }
        if ($d['tindak_lanjut']->isNotEmpty()) {
            $this->judul($out, 'TINDAK LANJUT STOK RUSAK', ['Tindakan', 'Kali', 'Jumlah', 'Nilai barang', 'Uang diterima']);
            foreach ($d['tindak_lanjut'] as $t) {
                $out[] = [\App\Services\StokRusakService::LABEL_TINDAKAN[$t->jenis] ?? $t->jenis, (int) $t->kali, (float) $t->qty, (float) $t->nilai, (float) $t->uang];
            }
        }
        if ($d['ganti_rugi']) {
            $this->judul($out, 'GANTI RUGI KARYAWAN', ['Dibebankan', 'Diterima', 'Sisa tagihan (semua periode)']);
            $out[] = [$d['ganti_rugi']->dibebankan, $d['ganti_rugi']->diterima, $d['ganti_rugi']->sisa];
        }
        $out[] = [];
        $out[] = [$this->footerDicetakOleh($this->namaUser)];

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        foreach ($this->judulBaris as $b) {
            $sheet->getStyle('A' . ($b - 1))->getFont()->setBold(true);
            $this->styleHeaderRow($sheet, "A{$b}:H{$b}");
        }
        $this->autoSizeAllColumns($sheet, 'A', 'H');

        return [];
    }
}
