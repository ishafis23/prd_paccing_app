<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as Contract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Pengganti Filament\Http\Responses\Auth\LogoutResponse: kembali ke
 * halaman login panel dengan URL dari APP_URL eksplisit (App\Support\
 * Url::panel), supaya prefix /public tidak hilang (kasus yang sama
 * dengan LoginResponse).
 */
class LogoutResponse implements Contract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

        return redirect()->to(\App\Support\Url::panel($panel->getId(), 'login'));
    }
}