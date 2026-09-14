<?php

namespace App\Livewire\Portal;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Dashboard Portal Customer (dev-plan/12 §3.6) — daftar Unit AC milik
 * customer + kapan terakhir dikerjakan, dan notice servis berikutnya.
 */
class Dashboard extends Component
{
    public function render()
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $customer->load([
            'addresses' => fn ($q) => $q->orderBy('id'),
            'addresses.acUnits' => fn ($q) => $q->orderBy('kode_unit'),
            'addresses.acUnits.latestOrderItem.order.teknisi',
        ]);

        // Fallback utk customer lama yg belum punya data alamat (dev-plan/14).
        if ($customer->addresses->isEmpty()) {
            $customer->load([
                'acUnits' => fn ($q) => $q->orderBy('kode_unit'),
                'acUnits.latestOrderItem.order.teknisi',
            ]);
        }

        $reminder = $customer->serviceReminders()->latest('id')->first();

        return view('livewire.portal.dashboard', [
            'customer' => $customer,
            'reminder' => $reminder,
        ])->layout('layouts.portal', ['title' => 'Dashboard']);
    }
}
