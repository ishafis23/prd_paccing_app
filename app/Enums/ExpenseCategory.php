<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Material = 'material';
    case Perawatan = 'perawatan';
    case Operasional = 'operasional';
}
