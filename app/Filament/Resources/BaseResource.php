<?php

namespace App\Filament\Resources;

use App\Support\Url;
use Filament\Facades\Filament;
use Filament\Resources\Resource;

/**
 * Basis semua resource panel admin.
 *
 * getUrl() dibangun ulang supaya URL ABSOLUT datang dari APP_URL eksplisit
 * (App\Support\Url::absolute), BUKAN request root ambient — di hosting
 * subfolder (public_html/<app>/public) root request kadang terdeteksi
 * tanpa prefix /public sehingga redirect setelah simpan (create/edit)
 * nyasar ke domain.com/admin/orders padahal seharusnya
 * domain.com/public/admin/orders (kasus yang sama dengan redirect login,
 * lihat App\Support\Url::panel). URL relatif (isAbsolute=false) dibiarkan
 * apa adanya seperti bawaan Filament.
 */
abstract class BaseResource extends Resource
{
    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        if (! $isAbsolute) {
            return parent::getUrl($name, $parameters, false, $panel, $tenant);
        }

        if (blank($panel) || Filament::getPanel($panel)->hasTenancy()) {
            $tenantParam = $tenant ?? Filament::getTenant();

            // Jangan kirim 'tenant' => null — itu bakal jadi query string
            // '?tenant=' yang tidak perlu di panel tanpa tenancy.
            if ($tenantParam !== null) {
                $parameters['tenant'] = $tenantParam;
            }
        }

        return Url::absolute(static::getRouteBaseName(panel: $panel).".{$name}", $parameters);
    }
}