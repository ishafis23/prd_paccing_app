<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cap tanggal & jam (waktu SERVER) langsung pada piksel foto dokumentasi supaya validasi
 * akurat (revisi 14 Nov 2026 poin 3). Waktu server tidak bisa dipalsukan dari app.
 *
 * Best-effort: format tak didukung / file rusak / gambar terlalu besar -> file asli dibiarkan.
 * Orientasi EXIF diterapkan sebelum digambar (GD membuang EXIF saat simpan ulang).
 */
class FotoTimestampService
{
    /** Batas piksel agar GD tidak menghabiskan memori. */
    private const MAKS_PIKSEL = 40_000_000;

    public static function aktif(): bool
    {
        return (bool) config('foto.timestamp', true) && function_exists('imagecreatefromstring');
    }

    /** Cap file di disk `public` (path relatif seperti hasil `store()`). */
    public function tempelPath(string $pathPublic, ?Carbon $waktu = null): bool
    {
        if (! self::aktif() || $pathPublic === '' || ! Storage::disk('public')->exists($pathPublic)) {
            return false;
        }

        return $this->tempelFile(Storage::disk('public')->path($pathPublic), $waktu);
    }

    /** Cap file di path absolut (di tempat). */
    public function tempelFile(string $file, ?Carbon $waktu = null): bool
    {
        if (! self::aktif() || ! is_file($file)) {
            return false;
        }

        try {
            $info = @getimagesize($file);
            if ($info === false || ($info[0] * $info[1]) > self::MAKS_PIKSEL) {
                return false;
            }

            $tipe = $info[2];
            if (! in_array($tipe, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                return false;
            }

            $gambar = @imagecreatefromstring((string) file_get_contents($file));
            if ($gambar === false) {
                return false;
            }

            if ($tipe === IMAGETYPE_JPEG) {
                $gambar = $this->putarSesuaiExif($gambar, $file);
            }

            $this->gambarCap($gambar, ($waktu ?? now())->format('d/m/Y H:i:s'));

            $ok = match ($tipe) {
                IMAGETYPE_PNG => imagepng($gambar, $file),
                IMAGETYPE_WEBP => imagewebp($gambar, $file, 88),
                default => imagejpeg($gambar, $file, 88),
            };
            imagedestroy($gambar);

            return (bool) $ok;
        } catch (Throwable) {
            return false;
        }
    }

    /** Putar gambar mengikuti tag Orientation EXIF. */
    private function putarSesuaiExif(\GdImage $gambar, string $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        $exif = @exif_read_data($file);
        $putar = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($putar === 0) {
            return $gambar;
        }

        $baru = imagerotate($gambar, $putar, 0);

        return $baru === false ? $gambar : $baru;
    }

    /** Gambar kotak gelap transparan + teks putih di pojok kanan bawah, ukuran proporsional. */
    private function gambarCap(\GdImage $gambar, string $teks): void
    {
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        // Render teks dengan font bawaan GD ke kanvas kecil, lalu perbesar sesuai ukuran foto.
        $font = 5;
        $tw = imagefontwidth($font) * strlen($teks);
        $th = imagefontheight($font);
        $pad = 3;

        $kecil = imagecreatetruecolor($tw + 2 * $pad, $th + 2 * $pad);
        imagefill($kecil, 0, 0, imagecolorallocate($kecil, 0, 0, 0));
        imagestring($kecil, $font, $pad, $pad, $teks, imagecolorallocate($kecil, 255, 255, 255));

        $targetLebar = (int) max(120, min($lebar * 0.34, $lebar - 8));
        $skala = $targetLebar / imagesx($kecil);
        $dw = (int) round(imagesx($kecil) * $skala);
        $dh = (int) round(imagesy($kecil) * $skala);
        $margin = (int) max(4, $lebar * 0.01);

        $x = max(0, $lebar - $dw - $margin);
        $y = max(0, $tinggi - $dh - $margin);

        // 70% buram: teks tetap terbaca di latar terang, foto di bawahnya tetap terlihat.
        imagealphablending($gambar, true);
        $tmp = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($tmp, $kecil, 0, 0, 0, 0, $dw, $dh, imagesx($kecil), imagesy($kecil));
        imagecopymerge($gambar, $tmp, $x, $y, 0, 0, $dw, $dh, 80);

        imagedestroy($kecil);
        imagedestroy($tmp);
    }
}
