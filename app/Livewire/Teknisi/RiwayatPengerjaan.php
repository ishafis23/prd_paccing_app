<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Models\Order;
use App\Support\EnumOptions;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat pengerjaan teknisi — bisa disaring per nama customer/tanggal/
 * jenis layanan, diminta client (dev-plan/teknisi/LIST Portal Teknisi.pdf
 * poin 1: sebelumnya semua order tercampur tanpa pemisah).
 */
class RiwayatPengerjaan extends Component
{
    use WithPagination;

    public string $cariNama = '';

    public string $tanggal = '';

    public string $jenisLayanan = '';

    public function updating($name): void
    {
        if (in_array($name, ['cariNama', 'tanggal', 'jenisLayanan'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset('cariNama', 'tanggal', 'jenisLayanan');
    }

    public function render()
    {
        $orders = Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->when(filled($this->cariNama), fn ($q) => $q->whereHas(
                'customer',
                fn ($qc) => $qc->where('nama', 'like', '%'.$this->cariNama.'%')
            ))
            ->when(filled($this->tanggal), fn ($q) => $q->whereDate('updated_at', $this->tanggal))
            ->when(filled($this->jenisLayanan), fn ($q) => $q->whereHas(
                'serviceCatalog',
                fn ($qc) => $qc->where('jenis_layanan', $this->jenisLayanan)
            ))
            ->with(['customer', 'serviceCatalog', 'latestPayment'])
            ->orderByDesc('updated_at')
            ->paginate(10);

        return view('livewire.teknisi.riwayat-pengerjaan', [
            'orders' => $orders,
            'opsiLayanan' => EnumOptions::for(ServiceType::class),
        ])
            ->layout('layouts.teknisi', ['title' => 'Riwayat Pengerjaan']);
    }
}
