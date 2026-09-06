<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;

class JadwalHariIni extends Component
{
    public function render()
    {
        $orders = Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [
                OrderStatus::Terjadwal->value,
                OrderStatus::MenujuLokasi->value,
                OrderStatus::Dikerjakan->value,
                OrderStatus::ButuhFollowup->value,
            ])
            ->with(['customer', 'serviceCatalog'])
            ->orderBy('tanggal_jadwal')
            ->get();

        return view('livewire.teknisi.jadwal-hari-ini', ['orders' => $orders])
            ->layout('layouts.teknisi', ['title' => 'Jadwal Saya']);
    }
}
