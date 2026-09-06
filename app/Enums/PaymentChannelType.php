<?php

namespace App\Enums;

enum PaymentChannelType: string
{
    case Qris = 'qris';
    case Bank = 'bank';
}
