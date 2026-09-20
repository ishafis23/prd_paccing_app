<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as Contract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Pengganti Filament\Http\Responses\Auth\LoginResponse.
 *
 * Redirect setelah login admin memakai URL panel dari APP_URL eksplisit
 * (App\Support\Url::panel), BUKAN Filament::getUrl() yang dibangun dari
 * root request ambient via route() — di hosting subfolder
 * (public_html/<app>/public) route() kadang kehilangan prefix /public,
 * sehingga setelah login admin nyasar ke domain.com/admin (404) padahal
 * panel berada di domain.com/public/admin (insiden subfolder 18 Sep).
 */
class LoginResponse implements Contract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

        return redirect()->intended(\App\Support\Url::panel($panel->getId()));
    }
}