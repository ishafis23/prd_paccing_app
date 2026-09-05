<?php

namespace App\Enums;

enum StockCategory: string
{
    case Sparepart = 'sparepart';
    case Consumable = 'consumable';
    case UnitAc = 'unit_ac';
}
