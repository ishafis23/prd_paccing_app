<?php

namespace App\Enums;

/**
 * Status pengajuan pulang cepat teknisi (dev-plan/hrd/02).
 */
enum EarlyLeaveStatus: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }
}
