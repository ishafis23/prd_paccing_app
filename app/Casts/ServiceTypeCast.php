<?php

namespace App\Casts;

use App\Enums\ServiceType;
use App\Support\ServiceTypeResolver;
use App\Support\ServiceTypeValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast jenis layanan (dev-plan/26): enum `ServiceType` untuk kode bawaan,
 * `ServiceTypeValue` untuk kode tambahan (dari tabel `service_types`).
 *
 * @implements CastsAttributes<ServiceType|ServiceTypeValue|null, mixed>
 */
class ServiceTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ServiceType|ServiceTypeValue|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $kode = (string) $value;

        return ServiceType::tryFrom($kode)
            ?? new ServiceTypeValue($kode, ServiceTypeResolver::label($kode) ?? $kode);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => ServiceTypeResolver::kode($value)];
    }
}
