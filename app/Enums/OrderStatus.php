<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Baru = 'baru';
    case Terjadwal = 'terjadwal';
    case MenujuLokasi = 'menuju_lokasi';
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';
    case ButuhFollowup = 'butuh_followup';
    case Batal = 'batal';
}
