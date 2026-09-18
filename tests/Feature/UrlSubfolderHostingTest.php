<?php

/**
 * Hosting subfolder (mis. domain.com/paccing/public) — route()/url() harus
 * patokan ke APP_URL, bukan root request, supaya redirect setelah login
 * tidak "lompat" ke domain root (lihat AppServiceProvider::boot()).
 */
it('route() dibangun dari APP_URL, bukan root request (aman utk hosting subfolder)', function () {
    expect(route('teknisi.jadwal'))->toStartWith(rtrim((string) config('app.url'), '/'));
    expect(url('/'))->toBe(rtrim((string) config('app.url'), '/'));
});
