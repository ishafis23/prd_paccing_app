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
        try {
            $relativePath = route($routeName, $parameters, false);
        } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
            // Fallback ke route() dengan absolute=true jika route tidak ada
            // (mungkin sedang proses registrasi atau ada perubahan model)
            return rtrim((string) config('app.url'), '/');
        }

        return rtrim((string) config('app.url'), '/').$relativePath;
    }

    /**
     * URL panel Filament (mis. 'admin') yang deterministik dari APP_URL —
     * pakai $panel->getPath() (bukan route()), supaya setelah login admin
     * tidak nyasar ke /admin tanpa prefix /public di hosting subfolder
     * (route() absen di sana kadang kehilangan prefix, sama seperti kasus
     * absolute() di atas). $path opsional utk halaman anak, mis.
     * panel('admin', 'login') → <APP_URL>/admin/login.
     */
    public static function panel(string $panelId, string $path = ''): string
    {
        $segmen = filament()->getPanel($panelId)->getPath();

        if ($path !== '') {
            $segmen .= '/'.ltrim($path, '/');
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($segmen, '/');
    }
}
