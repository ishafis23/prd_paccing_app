<?php

namespace App\Enums;

enum LeadSource: string
{
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';
    case Telepon = 'telepon';
    case Referral = 'referral';
    case Lainnya = 'lainnya';
}
