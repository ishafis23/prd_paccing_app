<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Memperkecil foto laporan sebelum dimasukkan ke PDF/preview (dev-plan/21 §6):
 * sisi terpanjang maksimal ±1000px, JPEG kualitas 72, memakai GD bawaan PHP.
 * Hasil di-cache di disk privat `<folder_laporan>/cache` (nama = hash path +
 * ukuran + waktu ubah file asli), jadi render berikutnya tidak menghitung
 * ulang. File asli TIDAK diubah. Tidak ada akses HTTP — semua dari disk lokal.
 */
class FotoKompresService
{
    public const SISI_MAKS = 1000;

    private const KUALITAS = 72;

    /**
     * @return array{path: string, lebar: int, tinggi: int}|null null bila file
     *                                                             asli tidak ada / bukan gambar yang bisa dibaca GD
     */
    public function kecilkan(string $pathPublic): ?array
    {
        $sumber = Storage::disk('public');
        $pathPublic = ltrim($pathPublic, '/');

        if ($pathPublic === '' || ! $sumber->exists($pathPublic)) {
            return null;
        }

        $asli = $sumber->path($pathPublic);
        $cache = $this->diskCache();
        $nama = $this->folderCache().'/'.sha1($pathPublic.'|'.filesize($asli).'|'.filemtime($asli)).'.jpg';

        if ($cache->exists($nama)) {
            return $this->infoCache($cache->path($nama));
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $isi = @file_get_contents($asli);
        $gambar = $isi === false ? false : @imagecreatefromstring($isi);
        unset($isi);

        if ($gambar === false) {
            return null;
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $skala = min(1.0, self::SISI_MAKS / max($lebar, $tinggi));
        $lebarBaru = max(1, (int) round($lebar * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        // Latar putih supaya PNG transparan tidak jadi hitam saat jadi JPEG.
        $kanvas = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        ob_start();
        imagejpeg($kanvas, null, self::KUALITAS);
        $jpeg = (string) ob_get_clean();
        imagedestroy($kanvas);

        if ($jpeg === '') {
            return null;
        }

        $cache->put($nama, $jpeg);
        StorageQuotaService::lupakanCache();

        return ['path' => $cache->path($nama), 'lebar' => $lebarBaru, 'tinggi' => $tinggiBaru];
    }

    public function folderCache(): string
    {
        return trim((string) config('penyimpanan.folder_laporan', 'laporan'), '/').'/cache';
    }

    private function diskCache(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('penyimpanan.disk_laporan', 'local'));
    }

    /**
     * @return array{path: string, lebar: int, tinggi: int}|null
     */
    private function infoCache(string $path): ?array
    {
        $ukuran = @getimagesize($path);

        return $ukuran === false ? null : ['path' => $path, 'lebar' => $ukuran[0], 'tinggi' => $ukuran[1]];
    }
}
