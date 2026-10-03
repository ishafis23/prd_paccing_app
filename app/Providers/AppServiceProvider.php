<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;

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
        // dev-plan/17 insiden 18 Sep: URL::forceRootUrl(config('app.url'))
        // sempat dipasang di sini utk perbaiki redirect login di hosting
        // subfolder (domain.com/paccing/public) — TAPI merusak SEMUA upload
        // foto Livewire (URL upload-file jadi dobel /paccing/public/paccing/
        // public/..., 404). Sebabnya: Livewire menandatangani URL upload
        // secara RELATIF lalu meng-absolut-kannya sendiri
        // (GenerateSignedUploadUrl::signedRoute() di
        // vendor/livewire/livewire) — begitu root dipaksa ke URL yang
        // punya path (/paccing/public), path itu ikut kehitung dobel.
        // Dicabut — root request Laravel yang asli (tanpa paksaan) sudah
        // benar utk struktur folder bertingkat biasa (bukan lewat reverse
        // proxy) seperti hosting ini. Kalau redirect login subfolder
        // ternyata masih salah tanpa ini, perbaikannya HARUS lebih
        // spesifik (bukan forceRootUrl global) — jangan pasang ulang tanpa
        // pertimbangkan efek ke Livewire file upload.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Akar insiden "foto harus diupload 2x": Livewire membangun URL
        // endpoint upload (`livewire.upload-file`) lewat `URL::to()` yang
        // mengandalkan deteksi base path dari request (SCRIPT_NAME). Di
        // hosting subfolder (domain.com/paccing/public) deteksi itu kadang
        // gagal tergantung cara domain diakses → URL upload kehilangan
        // prefix subfolder → 404 → kamera jatuh ke jalur cadangan
        // temp-photo. Kita override generator Livewire-nya: pakai base path
        // request kalau terdeteksi, kalau tidak fallback deterministik ke
        // APP_URL (pola sama `App\Support\Url::absolute`). Tanda tangan
        // Livewire divalidasi relatif (`hasValidRelativeSignature`), jadi
        // prefix path tidak mempengaruhi validitas.
        \Facades\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl::swap(
            new class extends GenerateSignedUploadUrl
            {
                public function signedRoute($name, $expiration, $parameters = [])
                {
                    $relative = URL::temporarySignedRoute($name, $expiration, $parameters, false);
                    $base = request()->getBaseUrl();

                    if ($base !== '') {
                        return request()->getSchemeAndHttpHost().$base.'/'.ltrim($relative, '/');
                    }

                    return rtrim((string) config('app.url'), '/').'/'.ltrim($relative, '/');
                }
            }
        );

        // Redirect login/logout panel admin memakai APP_URL eksplisit
        // (App\Support\Url::panel), bukan Filament::getUrl()/getLoginUrl()
        // yang dibangun dari root request ambient — di hosting subfolder
        // route() kadang kehilangan prefix /public sehingga setelah login
        // admin nyasar ke /admin tanpa /public (kasus sama dgn insiden
        // 18 Sep untuk teknisi). Daftar ulang kontrak di sini (boot, bukan
        // register) supaya menimpa binding bawaan FilamentServiceProvider.
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }
}
