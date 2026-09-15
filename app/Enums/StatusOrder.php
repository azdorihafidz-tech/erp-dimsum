<?php

namespace App\Enums;

enum StatusOrder: string
{
    case Pending = 'pending';
    case Proses = 'proses';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match($this) {
            StatusOrder::Pending => 'Menunggu',
            StatusOrder::Proses => 'Diproses',
            StatusOrder::Selesai => 'Selesai',
            StatusOrder::Dibatalkan => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            StatusOrder::Pending => 'bg-warning',
            StatusOrder::Proses => 'bg-info',
            StatusOrder::Selesai => 'bg-success',
            StatusOrder::Dibatalkan => 'bg-danger',
        };
    }
}
