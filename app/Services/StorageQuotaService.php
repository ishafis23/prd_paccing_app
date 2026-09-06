<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Kontrol penyimpanan foto (B22–B26): mengukur pemakaian disk public,
 * membandingkan dengan kuota, dan menolak unggahan baru saat penuh.
 */
class StorageQuotaService
{
    private const CACHE_KEY = 'penyimpanan.foto.terpakai_bytes';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('penyimpanan.disk', 'public'));
    }

    public function kuotaBytes(): int
    {
        return max(0, (int) config('penyimpanan.kuota_mb', 1024)) * 1024 * 1024;
    }

    /**
     * @param  bool  $segar  true = hitung ulang tanpa cache (lebih lambat).
     */
    public function pakaiBytes(bool $segar = false): int
    {
        if ($segar) {
            return $this->hitungBytes();
        }

        $ttl = (int) config('penyimpanan.cache_ttl_detik', 300);

        return (int) Cache::remember(self::CACHE_KEY, $ttl, fn (): int => $this->hitungBytes());
    }

    public function sisaBytes(): int
    {
        return max(0, $this->kuotaBytes() - $this->pakaiBytes());
    }

    public function persenTerpakai(): float
    {
        $kuota = $this->kuotaBytes();

        return $kuota <= 0 ? 100.0 : round(($this->pakaiBytes() / $kuota) * 100, 1);
    }

    public function cukupUntuk(int $tambahBytes): bool
    {
        return $this->pakaiBytes() + $tambahBytes <= $this->kuotaBytes();
    }

    /**
     * @throws BusinessRuleException bila pemakaian + tambahan melampaui kuota
     */
    public function pastikanCukup(int $tambahBytes): void
    {
        if ($this->cukupUntuk($tambahBytes)) {
            return;
        }

        throw new BusinessRuleException(sprintf(
            'Penyimpanan foto hampir penuh (terpakai %s dari kuota %s). Foto baru belum bisa diunggah — hubungi admin untuk menaikkan kuota atau tunggu pembersihan foto lama.',
            self::formatBytes($this->pakaiBytes()),
            self::formatBytes($this->kuotaBytes())
        ));
    }

    public function jumlahFileFoto(): int
    {
        return count($this->disk()->allFiles((string) config('penyimpanan.folder_foto', 'work-reports')));
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $satuan = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pangkat = (int) floor(log($bytes, 1024));
        $nilai = $bytes / (1024 ** min($pangkat, count($satuan) - 1));
        $desimal = ((float) $nilai === (float) (int) $nilai) ? 0 : 1;

        return number_format($nilai, $desimal, ',', '.').' '.$satuan[$pangkat];
    }

    private function hitungBytes(): int
    {
        $total = 0;

        foreach ($this->disk()->allFiles() as $file) {
            $total += (int) $this->disk()->size($file);
        }

        return $total;
    }
}
