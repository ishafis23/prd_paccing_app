<?php

namespace App\Enums;

/**
 * Status laporan setoran tunai teknisi (dev-plan/29 C2).
 */
enum CashHandoverStatus: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu verifikasi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }
}
