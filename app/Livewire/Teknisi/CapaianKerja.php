<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;

class CapaianKerja extends Component
{
    public function render()
    {
        $teknisiId = auth()->id();

        $bulanIni = Order::query()
            ->untukTeknisi($teknisiId)
            ->where('status', OrderStatus::Selesai->value)
            ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $totalSelesai = Order::query()
            ->untukTeknisi($teknisiId)
            ->where('status', OrderStatus::Selesai->value)
            ->count();

        return view('livewire.teknisi.capaian-kerja', [
            'bulanIni' => $bulanIni,
            'totalSelesai' => $totalSelesai,
        ])->layout('layouts.teknisi', ['title' => 'Capaian Kerja']);
    }
}
