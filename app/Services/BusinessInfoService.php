<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\BusinessInfo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Info identitas usaha (B37): baris tunggal business_infos + cache pendek.
 * Nama/logo dipakai brand panel admin, portal teknisi, landing, & resi.
 */
class BusinessInfoService
{
    use RestrictsByRole;

    public const DEFAULT_NAMA = 'Paccing Official';

    public const FOLDER_LOGO = 'business';

    public const PLACEHOLDER_ALAMAT = 'Jl. Contoh No. 1, Makassar';

    public const PLACEHOLDER_WA = '62811xxxxxxx';

    private const CACHE_KEY = 'info_usaha.data';

    /**
     * Ambil data usaha (selalu ada: bila baris belum ada, dibuat dengan
     * nilai default). Hasil di-cache 5 menit.
     */
    public function data(): BusinessInfo
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): BusinessInfo {
            $info = BusinessInfo::query()->first();

            if ($info) {
                return $info;
            }

            return BusinessInfo::create([
                'nama_usaha' => self::DEFAULT_NAMA,
                'alamat' => null, // placeholder lama tidak dipakai lagi (B37)
                'kontak_wa' => null,
                'email' => null,
                'nama_pemilik' => null,
                'logo_path' => null,
            ]);
        });
    }

    /**
     * Alamat untuk TAMPILAN publik; null bila belum diisi atau masih
     * placeholder contoh (agar tidak "bocor" ke landing/resi).
     */
    public function alamatTampil(): ?string
    {
        $alamat = trim((string) $this->data()->alamat);

        return ($alamat === '' || $alamat === self::PLACEHOLDER_ALAMAT) ? null : $alamat;
    }

    /**
     * Kontak WA untuk TAMPILAN publik; null bila belum diisi / masih
     * placeholder contoh.
     */
    public function kontakWaTampil(): ?string
    {
        $wa = trim((string) $this->data()->kontak_wa);

        if ($wa === '' || $wa === self::PLACEHOLDER_WA || str_contains($wa, 'xxxxx')) {
            return null;
        }

        return $wa;
    }

    public function namaUsaha(): string
    {
        return (string) $this->data()->nama_usaha;
    }

    /**
     * URL logo (relatif ke host aktif, mis. /storage/business/x.png) —
     * agar selalu termuat apa pun host/port yang dipakai (B37).
     */
    public function logoUrl(): ?string
    {
        $path = $this->data()->logo_path;

        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    /**
     * Perbarui info usaha (Owner/Admin).
     *
     * @param  array{nama_usaha: string, alamat?: ?string, kontak_wa?: ?string, email?: ?string, nama_pemilik?: ?string}  $data
     */
    public function perbarui(
        array $data,
        User $by,
        ?UploadedFile $logoBaru = null,
        bool $hapusLogo = false
    ): BusinessInfo {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $info = $this->data();

        $nama = trim((string) ($data['nama_usaha'] ?? ''));
        if ($nama === '') {
            throw new BusinessRuleException('Nama usaha wajib diisi.');
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessRuleException('Email tidak valid.');
        }

        $info->nama_usaha = $nama;
        $info->alamat = $this->nullJikaKosong($data['alamat'] ?? null);
        $info->kontak_wa = $this->nullJikaKosong($data['kontak_wa'] ?? null);
        $info->email = $this->nullJikaKosong($email);
        $info->nama_pemilik = $this->nullJikaKosong($data['nama_pemilik'] ?? null);
        $info->diubah_oleh = $by->id;

        $pathLama = $info->logo_path;

        if ($logoBaru) {
            $quota = app(StorageQuotaService::class);
            $quota->pastikanCukup($logoBaru->getSize());

            $pathBaru = $logoBaru->store(self::FOLDER_LOGO, 'public');
            $info->logo_path = $pathBaru;

            if ($pathLama && $pathLama !== $pathBaru) {
                Storage::disk('public')->delete($pathLama);
            }
            StorageQuotaService::lupakanCache();
        } elseif ($hapusLogo && $pathLama) {
            Storage::disk('public')->delete($pathLama);
            $info->logo_path = null;
            StorageQuotaService::lupakanCache();
        }

        $info->save();
        $this->lupakanCache();

        return $info->fresh();
    }

    public static function lupakanCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function nullJikaKosong(mixed $nilai): ?string
    {
        $nilai = is_string($nilai) ? trim($nilai) : $nilai;

        return ($nilai === null || $nilai === '') ? null : (string) $nilai;
    }
}
