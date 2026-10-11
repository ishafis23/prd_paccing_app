<?php

namespace App\Livewire\Portal;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Login Portal Corporate Customer (dev-plan/12 §3.6, dev-plan/22 Plan A) —
 * guard terpisah 'customer', 1 akun per customer (perusahaan), login pakai
 * username ATAU email + password (kolom di `customers`). Hanya jenis
 * `company` yang boleh masuk.
 */
class Login extends Component
{
    public string $identitas = '';

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
            'identitas' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $id = strtolower(trim($this->identitas));

        $akun = Customer::query()
            ->where(function ($query) use ($id): void {
                $query->whereRaw('lower(username) = ?', [$id])
                    ->orWhereRaw('lower(email) = ?', [$id]);
            })
            ->first();

        if (
            $akun === null
            || ! $akun->isCompany()
            || ! $akun->bisaLoginPortal()
            || ! Hash::check($this->password, $akun->password)
        ) {
            $this->addError('identitas', 'Username/email atau password salah, atau akses Portal belum diaktifkan. Hubungi admin.');

            return;
        }

        if ($akun->status === CustomerStatus::Nonaktif) {
            $this->addError('identitas', 'Akun Anda dinonaktifkan. Hubungi admin.');

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
