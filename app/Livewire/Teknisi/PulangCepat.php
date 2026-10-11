<?php

namespace App\Livewire\Teknisi;

use App\Exceptions\BusinessRuleException;
use App\Models\TechnicianEarlyLeave;
use App\Services\EarlyLeaveService;
use Livewire\Component;

/**
 * Pengajuan pulang cepat teknisi (dev-plan/hrd/02) — teknisi mengisi tanggal,
 * jam pulang, & alasan; HRD/Admin memverifikasi. Yang disetujui dihitung
 * setengah hari di penggajian.
 */
class PulangCepat extends Component
{
    public string $tanggal = '';

    public string $jam_pulang = '';

    public string $alasan = '';

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function ajukan(): void
    {
        $this->validate([
            'tanggal' => ['required', 'date'],
            'jam_pulang' => ['required'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(EarlyLeaveService::class)->ajukan(auth()->user(), $this->tanggal, $this->jam_pulang, $this->alasan);
            $this->reset(['jam_pulang', 'alasan']);
            session()->flash('status', 'Pengajuan pulang cepat dikirim — menunggu ACC HRD.');
        } catch (BusinessRuleException $e) {
            $this->addError('tanggal', $e->getMessage());
        }
    }

    public function render()
    {
        $riwayat = TechnicianEarlyLeave::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('tanggal')
            ->get();

        return view('livewire.teknisi.pulang-cepat', ['riwayat' => $riwayat])
            ->layout('layouts.teknisi', ['title' => 'Pulang Cepat']);
    }
}
