<?php

namespace App\Enums;

/**
 * Kategori ledger `technician_incentives` (dev-plan/15 §1a/§4): skema
 * insentif "Games 1-6" + denda telat.
 */
enum IncentiveKategori: string
{
    case Games1Hadir = 'games1_hadir';
    case Games2TitikPertama = 'games2_titik_pertama';
    case Games3CuciMotor = 'games3_cuci_motor';
    case Games4Kepulangan = 'games4_kepulangan';
    case Games5OmsetTim = 'games5_omset_tim';
    case Games6Unit = 'games6_unit';
    case DendaTelat = 'denda_telat';
    case Lainnya = 'lainnya';
}
