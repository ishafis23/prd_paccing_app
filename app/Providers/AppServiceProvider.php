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
    }
}
