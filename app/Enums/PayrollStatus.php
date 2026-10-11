<?php

namespace App\Enums;

/**
 * Status penggajian teknisi (dev-plan/hrd/02): draft → disetujui (ACC) →
 * dibayar. Slip baru boleh dilihat teknisi setelah disetujui.
 */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case Disetujui = 'disetujui';
    case Dibayar = 'dibayar';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Disetujui => 'Disetujui',
            self::Dibayar => 'Dibayar',
        };
    }
}
