<?php

use App\Providers\AppServiceProvider;
use Facades\Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl as GenerateSignedUploadUrlFacade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

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

/**
 * Bikin request yang "seolah" diakses dari subfolder. `$scriptName`
 * menentukan apakah Symfony bisa mendeteksi base path:
 *  - '/paccing/public/index.php' → base terdeteksi '/paccing/public'
 *  - '/index.php'               → base '' (deteksi gagal, kasus produksi)
 */
function bindRequestSubfolder(string $scriptName): Request
{
    $req = Request::create('https://mycompany.web.id/paccing/public/teknisi/order/5', 'GET');
    $req->server->set('SCRIPT_NAME', $scriptName);
    $req->server->set('SCRIPT_FILENAME', '/var/www'.$scriptName);
    $req->server->set('PHP_SELF', $scriptName);

    app()->instance('request', $req);
    URL::setRequest($req);

    return $req;
}

it('URL upload Livewire ikut subfolder saat base path terdeteksi', function () {
    config(['app.url' => 'https://mycompany.web.id/paccing/public']);
    (new AppServiceProvider(app()))->boot();
    bindRequestSubfolder('/paccing/public/index.php');

    $url = GenerateSignedUploadUrlFacade::forLocal();

    expect($url)->toContain('/paccing/public/livewire/upload-file')
        ->and($url)->not->toContain('/paccing/public/paccing/public');
});

it('URL upload Livewire fallback deterministik ke APP_URL saat base path tidak terdeteksi', function () {
    config(['app.url' => 'https://mycompany.web.id/paccing/public']);
    (new AppServiceProvider(app()))->boot();
    bindRequestSubfolder('/index.php');

    $url = GenerateSignedUploadUrlFacade::forLocal();

    expect($url)->toContain('mycompany.web.id/paccing/public/livewire/upload-file')
        ->and($url)->not->toContain('/paccing/public/paccing/public');
});
