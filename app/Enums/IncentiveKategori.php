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
    case Games7Manual = 'games7_manual';
    case DendaTelat = 'denda_telat';
    case Lainnya = 'lainnya';

    /**
     * Label utk tampilan slip/penggajian.
     */
    public function label(): string
    {
        return match ($this) {
            self::Games1Hadir => 'Bonus Game 1 (Hadir)',
            self::Games2TitikPertama => 'Bonus Game 2 (Titik Pertama)',
            self::Games3CuciMotor => 'Bonus Game 3 (Cuci Motor)',
            self::Games4Kepulangan => 'Bonus Game 4 (Kepulangan)',
            self::Games5OmsetTim => 'Bonus Game 5 (Omset Tim)',
            self::Games6Unit => 'Bonus Game 6 (Unit)',
            self::Games7Manual => 'Bonus Game 7',
            self::DendaTelat => 'Denda Keterlambatan',
            self::Lainnya => 'Bonus Lainnya',
        };
    }

    /**
     * Games 4,5,6 dicatat & dibayar MINGGUAN (tetap tampil di slip sebagai
     * pengurang karena sudah dibayar); lainnya BULANAN.
     */
    public function mingguan(): bool
    {
        return in_array($this, [self::Games4Kepulangan, self::Games5OmsetTim, self::Games6Unit], true);
    }
}
