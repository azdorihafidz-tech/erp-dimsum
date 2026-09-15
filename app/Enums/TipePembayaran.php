<?php

namespace App\Enums;

enum TipePembayaran: string
{
    case Tunai = 'tunai';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Gojek = 'gojek';
    case Grab = 'grab';

    public function label(): string
    {
        return match($this) {
            TipePembayaran::Tunai => 'Tunai',
            TipePembayaran::Transfer => 'Transfer Bank',
            TipePembayaran::Qris => 'QRIS',
            TipePembayaran::Gojek => 'Gojek (GoFood)',
            TipePembayaran::Grab => 'Grab (GrabFood)',
        };
    }

    /**
     * Kategori Kas yang menampung settlement metode ini. Gojek/Grab SENGAJA
     * tidak dapat Kas kategori sendiri (keputusan Owner, Tahap 3) — uangnya
     * cair ke rekening bank via transfer, jadi settle ke Kas kategori
     * "transfer" outlet yang sama seperti transfer bank biasa.
     */
    public function kasKategori(): string
    {
        return match($this) {
            TipePembayaran::Gojek, TipePembayaran::Grab => 'transfer',
            default => $this->value,
        };
    }
}
