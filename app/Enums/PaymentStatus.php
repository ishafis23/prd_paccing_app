<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case BelumBayar = 'belum_bayar';
    case Dp = 'dp';
    case Lunas = 'lunas';
}
