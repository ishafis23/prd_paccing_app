<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): string => $this->brandCss()
        );

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // B37: nama & logo usaha dari tabel business_infos (fallback teks).
            ->brandName(fn (): string => app(\App\Services\BusinessInfoService::class)->namaUsaha())
            ->brandLogo(fn (): ?HtmlString => $this->logoUsaha())
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Logo usaha dari storage bila sudah diunggah (B37). Markup: gambar +
     * nama usaha berdampingan di sidebar; CSS menyesuaikan ukuran di login
     * (lebih besar, tanpa teks) dan sidebar (logo + nama).
     */
    private function logoUsaha(): ?HtmlString
    {
        $service = app(\App\Services\BusinessInfoService::class);
        $url = $service->logoUrl();

        if ($url === null) {
            return null;
        }

        $nama = e($service->namaUsaha());

        return new HtmlString(sprintf(
            '<span class="fi-brand-inline"><img src="%s" alt="Logo %s" class="fi-brand-logo-img">'
            .'<span class="fi-brand-name">%s</span></span>',
            e($url),
            $nama,
            $nama
        ));
    }

    private function brandCss(): string
    {
        $css = <<<'CSS'
            /* Brand: logo + nama usaha berdampingan (sidebar). */
            .fi-brand-inline { display: flex; align-items: center; gap: 0.6rem; min-width: 0; line-height: 1; }
            .fi-brand-logo-img { display: block; height: 2rem; width: auto; object-fit: contain; flex-shrink: 0; }
            .fi-brand-name { min-width: 0; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 700; color: rgb(3 7 18); }
            .dark .fi-brand-name { color: #fff; }

            /* Login/simple page: logo lebih besar, teks nama disembunyikan. */
            .fi-simple-header .fi-logo { height: auto !important; }
            .fi-simple-header .fi-brand-inline { display: block; }
            .fi-simple-header .fi-brand-logo-img { height: 4.5rem !important; width: auto; margin: 0 auto; }
            .fi-simple-header .fi-brand-name { display: none !important; }
            CSS;

        return '<style>'.$css.'</style>';
    }
}
