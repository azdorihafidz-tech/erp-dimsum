<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Notifikasi lonceng Stok Opname (diajukan, ditolak, disetujui, pengingat). */
class StokOpnameNotification extends Notification
{
    public function __construct(
        private string $judul,
        private string $pesan,
        private string $url,
        private string $warna = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'    => $this->judul,
            'message'  => $this->pesan,
            'icon'     => 'bi-clipboard-check',
            'color'    => $this->warna,
            'url'      => $this->url,
            'kategori' => 'stok',
        ];
    }
}
