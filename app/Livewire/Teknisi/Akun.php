<?php

namespace App\Livewire\Teknisi;

use Livewire\Component;

class Akun extends Component
{
    public function render()
    {
        return view('livewire.teknisi.akun', ['user' => auth()->user()])
            ->layout('layouts.teknisi', ['title' => 'Akun Saya']);
    }
}
