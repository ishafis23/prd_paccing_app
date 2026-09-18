<?php

namespace App\Support;

class Url
{
    /**
     * route($name, absolute: true) mengandalkan root request saat ini —
     * di hosting subfolder (public_html/paccing/public) ini kadang tidak
     * konsisten terdeteksi (tergantung cara user mengakses domain),
     * menyebabkan redirect login kehilangan prefix /paccing/public.
     * Dibangun eksplisit dari APP_URL supaya deterministik, TANPA
     * menyentuh URL::forceRootUrl() (itu yang merusak upload Livewire —
     * lihat AppServiceProvider).
     */
    public static function absolute(string $routeName, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').route($routeName, $parameters, false);
    }
}
