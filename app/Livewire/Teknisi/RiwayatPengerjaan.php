<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithPagination;

class RiwayatPengerjaan extends Component
{
    use WithPagination;

    public function render()
    {
        $orders = Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->with(['customer', 'serviceCatalog', 'latestPayment'])
            ->orderByDesc('updated_at')
            ->paginate(10);

        return view('livewire.teknisi.riwayat-pengerjaan', ['orders' => $orders])
            ->layout('layouts.teknisi', ['title' => 'Riwayat Pengerjaan']);
    }
}
