<?php

namespace App\Enums;

enum RoleName: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Finance = 'finance';
    case Hr = 'hr';
    case Teknisi = 'teknisi';
}
