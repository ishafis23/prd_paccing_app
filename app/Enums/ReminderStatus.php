<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case BelumJatuhTempo = 'belum_jatuh_tempo';
    case SiapDihubungi = 'siap_dihubungi';
    case SudahDihubungi = 'sudah_dihubungi';
    case Selesai = 'selesai';
}
