<?php

use App\Providers\AppServiceProvider;

/**
 * Insiden 18 Sep 2026: `URL::forceRootUrl(config('app.url'))` sempat
 * dipasang di `AppServiceProvider::boot()` utk perbaiki redirect login di
 * hosting subfolder (domain.com/paccing/public) — tapi merusak SEMUA
 * upload foto Livewire: `GenerateSignedUploadUrl::signedRoute()`
 * (vendor/livewire/livewire) menandatangani URL upload secara RELATIF lalu
 * meng-absolut-kannya sendiri lewat `URL::to()` — begitu root dipaksa ke
 * URL yang punya path (`/paccing/public`), path itu ikut kehitung dobel
 * (404 "…/paccing/public/paccing/public/livewire/upload-file"). Dicabut.
 *
 * Tes ini menjaga supaya `forceRootUrl` (atau perbaikan serupa yang punya
 * efek sama) tidak dipasang lagi tanpa disadari akibatnya ke Livewire.
 */
it('AppServiceProvider tidak lagi forceRootUrl ke APP_URL yang berisi subfolder', function () {
    config(['app.url' => 'https://mycompany.web.id/paccing/public']);

    (new AppServiceProvider(app()))->boot();

    // Kalau forceRootUrl aktif, url() akan SELALU ikut config('app.url')
    // apa pun root request sebenarnya. Root request tes ini (localhost)
    // tidak mengandung "/paccing/public" — jadi kalau ini muncul di sini,
    // berarti forceRootUrl terpasang lagi.
    expect(url('/foo'))->not->toContain('/paccing/public');
});
