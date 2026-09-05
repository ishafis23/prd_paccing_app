<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Lead = 'lead';
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';
}
