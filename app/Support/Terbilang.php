<?php

namespace App\Support;

/**
 * Angka → kata bahasa Indonesia (mis. 1500000 → "Satu Juta Lima Ratus Ribu Rupiah").
 */
class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float|int $angka): string
    {
        $n = (int) round($angka);
        $minus = $n < 0;
        $kata = $n === 0 ? 'nol' : self::kata(abs($n));

        return ucwords(($minus ? 'minus ' : '').$kata).' Rupiah';
    }

    private static function kata(int $n): string
    {
        if ($n < 12) {
            return self::SATUAN[$n];
        }

        if ($n < 20) {
            return self::kata($n - 10).' belas';
        }

        if ($n < 100) {
            return trim(self::kata(intdiv($n, 10)).' puluh '.self::kata($n % 10));
        }

        if ($n < 200) {
            return trim('seratus '.self::kata($n - 100));
        }

        if ($n < 1000) {
            return trim(self::kata(intdiv($n, 100)).' ratus '.self::kata($n % 100));
        }

        if ($n < 2000) {
            return trim('seribu '.self::kata($n - 1000));
        }

        if ($n < 1_000_000) {
            return trim(self::kata(intdiv($n, 1000)).' ribu '.self::kata($n % 1000));
        }

        if ($n < 1_000_000_000) {
            return trim(self::kata(intdiv($n, 1_000_000)).' juta '.self::kata($n % 1_000_000));
        }

        if ($n < 1_000_000_000_000) {
            return trim(self::kata(intdiv($n, 1_000_000_000)).' miliar '.self::kata($n % 1_000_000_000));
        }

        return trim(self::kata(intdiv($n, 1_000_000_000_000)).' triliun '.self::kata($n % 1_000_000_000_000));
    }
}
