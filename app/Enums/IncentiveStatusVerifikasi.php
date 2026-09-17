<?php

namespace App\Enums;

/**
 * Verifikasi foto bukti insentif (dev-plan/15, B55): entri berfoto masuk
 * `Menunggu` dulu, Admin/HR Setujui/Tolak; yang ditolak dikeluarkan dari
 * total gaji.
 */
enum IncentiveStatusVerifikasi: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
}
