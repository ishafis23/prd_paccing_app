<?php

namespace App\Support;

use App\Enums\IncomeCategory;
use App\Enums\ServiceType;
use App\Models\ServiceTypeOption;

/**
 * Satu pintu jenis layanan (dev-plan/26): gabungan enum `ServiceType` bawaan
 * (read-only) + tabel `service_types` (tambahan yang dikelola admin).
 */
class ServiceTypeResolver
{
    /**
     * Opsi select value => label: bawaan (enum) lalu tambahan (urut).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $bawaan = collect(ServiceType::cases())
            ->mapWithKeys(fn (ServiceType $t): array => [$t->value => self::labelBawaan($t)]);

        $tambahan = ServiceTypeOption::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('label')
            ->pluck('label', 'kode');

        return $bawaan->merge($tambahan)->all();
    }

    public static function label(?string $kode): ?string
    {
        if ($kode === null || $kode === '') {
            return null;
        }

        $bawaan = ServiceType::tryFrom($kode);

        if ($bawaan !== null) {
            return self::labelBawaan($bawaan);
        }

        return ServiceTypeOption::query()->where('kode', $kode)->value('label')
            ?? str($kode)->headline()->toString();
    }

    /**
     * Komponen omset (jasa/material) sebuah jenis layanan.
     */
    public static function komponen(ServiceType|ServiceTypeValue|string|null $jenis): IncomeCategory
    {
        if ($jenis instanceof ServiceType) {
            return IncomeCategory::untukLayanan($jenis);
        }

        $kode = $jenis instanceof ServiceTypeValue ? $jenis->value : (string) $jenis;

        $tambahan = ServiceTypeOption::query()->where('kode', $kode)->first();

        if ($tambahan !== null) {
            return $tambahan->komponen;
        }

        $bawaan = ServiceType::tryFrom($kode);

        return $bawaan !== null ? IncomeCategory::untukLayanan($bawaan) : IncomeCategory::Material;
    }

    /**
     * Jenis layanan berupa misi/operasional yang dikecualikan dari omset & unit
     * (hanya jenis tambahan yang bisa ditandai begini).
     */
    public static function bukanOmset(ServiceType|ServiceTypeValue|string|null $jenis): bool
    {
        $kode = self::kode($jenis);

        if ($kode === null || ServiceType::tryFrom($kode) !== null) {
            return false;
        }

        return ServiceTypeOption::query()->where('kode', $kode)->where('bukan_omset', true)->exists();
    }

    /**
     * Kode jenis layanan valid (bawaan atau tambahan aktif)?
     */
    public static function valid(string $kode): bool
    {
        return ServiceType::tryFrom($kode) !== null
            || ServiceTypeOption::query()->where('kode', $kode)->exists();
    }

    /**
     * Normalisasi nilai apa pun (enum/value/string) menjadi kode string.
     */
    public static function kode(ServiceType|ServiceTypeValue|string|null $jenis): ?string
    {
        if ($jenis === null) {
            return null;
        }

        if ($jenis instanceof ServiceType) {
            return $jenis->value;
        }

        return $jenis instanceof ServiceTypeValue ? $jenis->value : (string) $jenis;
    }

    private static function labelBawaan(ServiceType $jenis): string
    {
        return str($jenis->value)->headline()->toString();
    }
}
