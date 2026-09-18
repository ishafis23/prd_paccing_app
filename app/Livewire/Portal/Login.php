<?php

namespace App\Livewire\Portal;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Login Portal Customer (dev-plan/12 §3.6) — guard terpisah 'customer',
 * 1 akun per customer (keputusan 13 Sept), pakai customers.email/password.
 */
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        if (Auth::guard('customer')->check()) {
            $this->redirect(Url::absolute('portal.dashboard'), navigate: false);
        }
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $akun = Customer::query()
            ->whereRaw('lower(email) = ?', [strtolower(trim($this->email))])
            ->first();

        if ($akun === null || ! $akun->bisaLoginPortal() || ! Hash::check($this->password, $akun->password)) {
            $this->addError('email', 'Email atau password salah, atau akses Portal belum diaktifkan. Hubungi admin.');

            return;
        }

        if ($akun->status === CustomerStatus::Nonaktif) {
            $this->addError('email', 'Akun Anda dinonaktifkan. Hubungi admin.');

            return;
        }

        Auth::guard('customer')->login($akun);
        session()->regenerate();

        $this->redirect(Url::absolute('portal.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.portal.login')->layout('layouts.guest', ['title' => 'Portal Customer']);
    }
}
