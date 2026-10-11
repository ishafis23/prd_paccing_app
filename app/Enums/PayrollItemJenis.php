<?php

namespace App\Enums;

/**
 * Jenis baris rincian penggajian (payroll_run_items).
 */
enum PayrollItemJenis: string
{
    case Bonus = 'bonus';
    case Potongan = 'potongan';

    public function label(): string
    {
        return $this === self::Bonus ? 'Bonus' : 'Potongan';
    }
}
