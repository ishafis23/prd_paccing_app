<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hosting subfolder (mis. domain.com/paccing/public): tanpa ini,
        // url()/route()/redirect()->route() memakai root request yang bisa
        // salah di balik reverse proxy (subfolder ke-strip sebelum sampai
        // PHP) — patokkan semuanya ke APP_URL, sama seperti asset() yang
        // sudah begini secara default (lihat BusinessInfoService).
        if (filled(config('app.url'))) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
