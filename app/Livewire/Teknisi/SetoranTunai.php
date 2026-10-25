<?php

namespace App\Livewire\Teknisi;

use App\Exceptions\BusinessRuleException;
use App\Models\CashHandover;
use App\Models\Order;
use App\Services\CashHandoverService;
use App\Services\StorageQuotaService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class SetoranTunai extends Component
{
    use WithFileUploads;

    public ?int $pilihOrderId = null;
    public $nominal = '';
    public string $diserahkanKe = '';
    public string $tanggalSetor = '';
    public string $catatan = '';
    public $foto;

    public function mount(): void
    {
        $this->tanggalSetor = today()->toDateString();
        $this->pilihOrderId = app(CashHandoverService::class)->kewajiban(auth()->user())->first()?->id;
    }

    public function simpan(): void
    {
        $this->validate([
            'pilihOrderId' => ['required', 'integer'],
            'nominal' => ['required', 'numeric', 'min:1000'],
            'diserahkanKe' => ['required', 'string', 'min:3', 'max:150'],
            'tanggalSetor' => ['required', 'date', 'before_or_equal:today'],
            'foto' => ['required', 'image', 'max:5120'],
            'catatan' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($this->pilihOrderId);
        try {
            app(StorageQuotaService::class)->pastikanCukup((int) $this->foto->getSize());
            $path = $this->foto->store('setoran-tunai', 'public');
            app(CashHandoverService::class)->catat($order, auth()->user(), [
                'nominal' => $this->nominal,
                'diserahkan_ke' => $this->diserahkanKe,
                'tanggal_setor' => $this->tanggalSetor,
                'catatan' => $this->catatan,
                'foto_path' => $path,
            ]);
            StorageQuotaService::lupakanCache();
            session()->flash('status', 'Laporan setoran tunai berhasil dikirim untuk verifikasi.');
            $this->reset(['pilihOrderId', 'nominal', 'diserahkanKe', 'catatan', 'foto']);
            $this->tanggalSetor = today()->toDateString();
            $this->pilihOrderId = app(CashHandoverService::class)->kewajiban(auth()->user())->first()?->id;
            $this->dispatch('setoran-tersimpan');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $user = auth()->user();
        return view('livewire.teknisi.setoran-tunai', [
            'kewajiban' => app(CashHandoverService::class)->kewajiban($user),
            'telatIds' => app(CashHandoverService::class)->telat($user)->pluck('id')->all(),
            'riwayat' => CashHandover::query()->where('teknisi_id', $user->id)->with(['order.customer'])->latest('id')->limit(10)->get(),
        ])->layout('layouts.teknisi', ['title' => 'Setoran Tunai']);
    }
}
