<?php

use App\Exceptions\BusinessRuleException;
use App\Services\GoogleMapsLinkService;
use Illuminate\Support\Facades\Http;

it('mengekstrak koordinat dari link Google Maps lengkap (@lat,lng)', function () {
    Http::fake([
        'google.com/*' => Http::response('<html>dummy</html>', 200),
    ]);

    $service = app(GoogleMapsLinkService::class);
    $coords = $service->resolveCoordinates('https://www.google.com/maps/@-5.1476651,119.4327324,17z');

    expect($coords['lat'])->toBe(-5.1476651);
    expect($coords['lng'])->toBe(119.4327324);
});

it('mengekstrak koordinat pin (!3d!4d) dari short link goo.gl setelah redirect', function () {
    Http::fake([
        'maps.app.goo.gl/*' => Http::response(
            '<html>...!3d-5.1476651!4d119.4327324...</html>',
            200,
        ),
    ]);

    $service = app(GoogleMapsLinkService::class);
    $coords = $service->resolveCoordinates('https://maps.app.goo.gl/3F7MPCjWWm8fnGVa7');

    expect($coords['lat'])->toBe(-5.1476651);
    expect($coords['lng'])->toBe(119.4327324);
});

it('menolak link yang bukan domain Google Maps', function () {
    $service = app(GoogleMapsLinkService::class);

    expect(fn () => $service->resolveCoordinates('https://evil.example.com/internal-admin'))
        ->toThrow(BusinessRuleException::class);
});

it('melempar error jika koordinat tidak ditemukan di link', function () {
    Http::fake([
        'google.com/*' => Http::response('<html>tidak ada koordinat di sini</html>', 200),
    ]);

    $service = app(GoogleMapsLinkService::class);

    expect(fn () => $service->resolveCoordinates('https://www.google.com/maps/search/?api=1&query=kantor'))
        ->toThrow(BusinessRuleException::class);
});
