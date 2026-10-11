<?php

namespace App\Livewire\Teknisi;

use App\Exceptions\BusinessRuleException;
use App\Services\StorageQuotaService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Akun extends Component
{
    use WithFileUploads;

    /** File foto profil sementara (sebelum disimpan). */
    public $fotoProfil;

    public function simpanFotoProfil(): void
    {
        $this->validate(['fotoProfil' => ['required', 'image', 'max:4096']]);

        $user = auth()->user();

        try {
            app(StorageQuotaService::class)->pastikanCukup((int) $this->fotoProfil->getSize());
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $lama = $user->foto_profil;
        $path = $this->fotoProfil->store('foto-profil', 'public');

        $user->foto_profil = $path;
        $user->save();

        if ($lama && $lama !== $path) {
            Storage::disk('public')->delete($lama);
        }

        StorageQuotaService::lupakanCache();
        $this->reset('fotoProfil');
        session()->flash('status', 'Foto profil tersimpan.');
    }

    public function render()
    {
        return view('livewire.teknisi.akun', ['user' => auth()->user()])
            ->layout('layouts.teknisi', ['title' => 'Akun Saya']);
    }
}
