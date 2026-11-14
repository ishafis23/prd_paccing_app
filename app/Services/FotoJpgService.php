<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Revisi 14 Nov 2026 poin 24 — bukti screenshot chat disimpan SELALU sebagai JPG (ringan, seragam).
 * Screenshot HP umumnya PNG, jadi teknisi boleh memilih gambar apa pun; server mengubahnya ke JPG
 * (latar putih untuk PNG transparan, sisi terpanjang maks 1600 px). Bila GD tidak bisa membaca
 * formatnya (mis. HEIC), file asli disimpan apa adanya.
 */
class FotoJpgService
{
    public const SISI_MAKS = 1600;

    public const KUALITAS = 85;

    /** @return string path relatif di disk `public` */
    public function simpan(UploadedFile $file, string $direktori): string
    {
        $isi = @file_get_contents($file->getRealPath());
        $gambar = $isi !== false ? @imagecreatefromstring($isi) : false;

        if ($gambar === false) {
            return $file->store($direktori, 'public');
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $skala = min(1.0, self::SISI_MAKS / max($lebar, $tinggi));
        $w = max(1, (int) round($lebar * $skala));
        $h = max(1, (int) round($tinggi * $skala));

        $kanvas = imagecreatetruecolor($w, $h);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $w, $h, $lebar, $tinggi);

        ob_start();
        imagejpeg($kanvas, null, self::KUALITAS);
        $jpg = (string) ob_get_clean();

        $path = trim($direktori, '/').'/'.Str::random(40).'.jpg';
        Storage::disk('public')->put($path, $jpg);

        return $path;
    }
}
