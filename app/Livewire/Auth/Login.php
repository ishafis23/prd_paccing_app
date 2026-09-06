<?php

namespace App\Livewire\Auth;

use App\Enums\UserStatus;
use App\Models\User as UserModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $akun = UserModel::query()->where('email', strtolower(trim($this->email)))->first();

        if ($akun && $akun->status !== UserStatus::Aktif) {
            $this->addError('email', 'Akun Anda dinonaktifkan. Hubungi admin.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'Email atau password salah.');

            return;
        }

        session()->regenerate();

        $user = Auth::user();

        if ($user->canAccessPanel(filament()->getPanel('admin'))) {
            $this->redirect('/admin', navigate: false);

            return;
        }

        if ($user->hasRole('teknisi')) {
            $this->redirect('/teknisi', navigate: false);

            return;
        }

        Auth::logout();
        $this->addError('email', 'Akun ini tidak memiliki akses ke sistem.');
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.guest', ['title' => 'Login']);
    }
}
