<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Berita Acara Stok Opname (Excel) — data dari StokOpnameService::dataBeritaAcara(). */
class BeritaAcaraStokOpnameExport implements FromArray, WithStyles, WithTitle
{
    use HasLaporanStyles;

    private int $barisHeader = 0;
    private int $barisAkhirTabel = 0;

    public function __construct(private array $d, private ?string $namaUser = null) {}

    public function title(): string
    {
        return 'Berita Acara';
    }

    public function array(): array
    {
        $o = $this->d['o'];
        $rk = $this->d['ringkasan'];
        $out = [
            ['BERITA ACARA STOK OPNAME' . ($this->d['final'] ? '' : ' (SEMENTARA — belum disetujui)')],
            ['Nomor', $o->nomor],
            ['Lokasi', $o->cabang?->nama_cabang],
            ['Cakupan', $o->labelCakupan()],
            ['Dimulai', $o->created_at->format('d/m/Y H:i') . ' oleh ' . ($o->pembuat?->name ?? '-')],
            ['Penghitung', implode(', ', $this->d['penghitung'])],
            ['Diajukan', $o->diajukan_at ? $o->diajukan_at->format('d/m/Y H:i') . ' oleh ' . ($o->pengaju?->name ?? '-') : '-'],
            ['Disetujui', $o->disetujui_at ? $o->disetujui_at->format('d/m/Y H:i') . ' oleh ' . ($o->penyetuju?->name ?? '-') : '-'],
            [],
        ];
        $this->barisHeader = count($out) + 1;
        $out[] = ['Kode', 'Barang', 'Satuan', 'Stok Sistem', 'Fisik', 'Selisih', 'Harga Pokok', 'Nilai Selisih', 'Penyebab', 'Dihitung oleh'];
        foreach ($this->d['baris'] as $b) {
            $out[] = [$b['kode'], $b['nama'], $b['satuan'], $b['sistem'], $b['fisik'], $b['selisih'], $b['harga'], $b['nilai'], $b['penyebab'], $b['penghitung']];
        }
        $this->barisAkhirTabel = count($out);
        $out[] = [];
        $out[] = ['RINGKASAN'];
        $out[] = ['Barang dihitung', $rk['dihitung']];
        $out[] = ['Cocok', $rk['cocok']];
        $out[] = ['Kurang', $rk['kurang']];
        $out[] = ['Lebih', $rk['lebih']];
        $out[] = ['Nilai kurang', $rk['nilai_kurang']];
        $out[] = ['Nilai lebih', $rk['nilai_lebih']];
        $out[] = ['Nilai bersih', $rk['nilai_bersih']];
        $out[] = ['Akurasi (barang cocok)', $rk['akurasi'] !== null ? $rk['akurasi'] . '%' : '-'];
        $out[] = ['Akurasi (nilai)', $rk['akurasi_nilai'] !== null ? $rk['akurasi_nilai'] . '%' : '-'];
        if ($this->d['dikeluarkan']) {
            $out[] = [];
            $out[] = ['TIDAK DIHITUNG (dikeluarkan dari opname)'];
            foreach ($this->d['dikeluarkan'] as $k) {
                $out[] = [$k['kode'], $k['nama'], $k['alasan']];
            }
        }
        $out[] = [];
        $out[] = [$this->footerDicetakOleh($this->namaUser)];

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $this->styleHeaderRow($sheet, "A{$this->barisHeader}:J{$this->barisHeader}");
        $this->styleRupiahColumn($sheet, 'G');
        $this->styleRupiahColumn($sheet, 'H');
        $this->autoSizeAllColumns($sheet, 'A', 'J');

        return [];
    }
}
