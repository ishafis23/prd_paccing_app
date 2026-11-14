<?php

namespace App\Services;

use App\Models\TeknisiExpense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Penyimpanan foto bukti pengeluaran + proteksi foto kembar (revisi 14 Nov 2026, poin 4).
 *
 * Kembar dideteksi dua lapis:
 *  1. sha256 isi file  -> file yang sama persis;
 *  2. hash perseptual (average-hash 8x8, GD) -> foto yang sama tapi disimpan ulang/dikompres
 *     (jarak Hamming <= AMBANG_HAMMING).
 * Pembanding: seluruh pengeluaran ber-foto (semua teknisi) dalam JANGKAUAN_HARI terakhir.
 */
class FotoBuktiService
{
    public const DISK = 'public';

    public const FOLDER = 'pengeluaran-bukti';

    public const AMBANG_HAMMING = 4;

    public const JANGKAUAN_HARI = 120;

    /**
     * Simpan foto bila bukan kembar.
     *
     * @param  int|null  $abaikanId  pengeluaran yang sedang diubah (fotonya sendiri tidak dihitung kembar)
     * @return array{foto_path: string, foto_hash: string, foto_phash: ?string, foto_diunggah_pada: \Illuminate\Support\Carbon}
     *
     * @throws ValidationException
     */
    public function simpan(UploadedFile $file, ?int $abaikanId = null): array
    {
        $isi = (string) file_get_contents($file->getRealPath());
        $hash = hash('sha256', $isi);
        $phash = $this->phash($isi);
        unset($isi);

        if ($this->kembar($hash, $phash, $abaikanId)) {
            throw ValidationException::withMessages([
                'foto' => ['Foto ini sudah pernah dipakai sebagai bukti pengeluaran. Ambil foto bukti yang baru.'],
            ]);
        }

        app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());
        $path = $file->store(self::FOLDER, self::DISK);
        // Cap waktu server SETELAH hash dihitung (hash tersimpan tetap dari foto asli).
        app(FotoTimestampService::class)->tempelPath($path);
        StorageQuotaService::lupakanCache();

        return [
            'foto_path' => $path,
            'foto_hash' => $hash,
            'foto_phash' => $phash,
            'foto_diunggah_pada' => now(),
        ];
    }

    public function hapusFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function kembar(string $hash, ?string $phash, ?int $abaikanId = null): bool
    {
        $query = TeknisiExpense::query()
            ->whereNotNull('foto_hash')
            ->where('created_at', '>=', now()->subDays(self::JANGKAUAN_HARI))
            ->when($abaikanId !== null, fn ($q) => $q->where('id', '!=', $abaikanId));

        if ((clone $query)->where('foto_hash', $hash)->exists()) {
            return true;
        }

        if ($phash === null) {
            return false;
        }

        return $query->whereNotNull('foto_phash')->pluck('foto_phash')
            ->contains(fn (string $lain): bool => $this->jarak($phash, $lain) <= self::AMBANG_HAMMING);
    }

    /** Average-hash 64 bit (16 digit hex); null bila GD/gambar tidak terbaca. */
    public function phash(string $isi): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $gambar = @imagecreatefromstring($isi);
        if ($gambar === false) {
            return null;
        }

        $kecil = imagecreatetruecolor(8, 8);
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, 8, 8, imagesx($gambar), imagesy($gambar));

        $abu = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($kecil, $x, $y);
                $abu[] = (int) round((0.299 * (($rgb >> 16) & 0xFF)) + (0.587 * (($rgb >> 8) & 0xFF)) + (0.114 * ($rgb & 0xFF)));
            }
        }

        // Gambar nyaris polos tidak punya ciri khas -> semua tampak "kembar"; lewati hash perseptual.
        if (max($abu) - min($abu) < 12) {
            return null;
        }

        $rata = array_sum($abu) / 64;
        $bit = '';
        foreach ($abu as $v) {
            $bit .= $v >= $rata ? '1' : '0';
        }

        $hex = '';
        foreach (str_split($bit, 4) as $nibble) {
            $hex .= dechex(bindec($nibble));
        }

        return $hex;
    }

    /** Jarak Hamming dua hash hex 16 digit. */
    public function jarak(string $a, string $b): int
    {
        $beda = 0;
        for ($i = 0, $n = min(strlen($a), strlen($b)); $i < $n; $i++) {
            $beda += substr_count(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), '1');
        }

        return $beda;
    }
}
