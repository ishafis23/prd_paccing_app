<?php

namespace App\Enums;

/**
 * Status kasbon teknisi (dev-plan/28).
 */
enum AdvanceStatus: string
{
    case Aktif = 'aktif';
    case Lunas = 'lunas';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Lunas => 'Lunas',
        };
    }
}
