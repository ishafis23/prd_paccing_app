<?php

namespace App\Livewire\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\CustomerAcUnit;
use App\Services\InvoiceService;
use App\Services\PortalAksesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Ringkasan Portal Corporate Customer (dev-plan/22 §4.1) — jumlah order bulan
 * ini, unit terdaftar, saldo invoice belum lunas, jadwal berikutnya, plus daftar
 * unit AC per alamat (dev-plan/12 §3.6 & dev-plan/14).
 */
class Dashboard extends Component
{
    public function render()
    {
        $akses = app(PortalAksesService::class);
        $invoices = app(InvoiceService::class);

        $customer = $akses->customer();

        if (! Auth::guard('customer')->check()) {
            abort(403);
        }

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

        $orderBulanIni = $akses->orderQuery()
            ->whereBetween('tanggal_jadwal', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $totalUnit = CustomerAcUnit::query()->where('customer_id', $customer->id)->count();

        $daftarInvoice = $akses->invoiceQuery()->orderByDesc('tanggal')->orderByDesc('id')->get();
        $daftarInvoice->each(fn ($invoice) => $invoices->segarkanStatus($invoice));
        $saldoTerbuka = $daftarInvoice
            ->where('status', InvoiceStatus::Terkirim)
            ->sum(fn ($invoice) => $invoice->saldo());

        $jadwalBerikutnya = $akses->orderQuery()
            ->with('customerAddress')
            ->whereIn('status', [
                OrderStatus::Terjadwal->value,
                OrderStatus::MenujuLokasi->value,
                OrderStatus::Dikerjakan->value,
            ])
            ->whereDate('tanggal_jadwal', '>=', today()->toDateString())
            ->orderBy('tanggal_jadwal')
            ->orderBy('jam_jadwal')
            ->first();

        return view('livewire.portal.dashboard', [
            'customer' => $customer,
            'reminder' => $reminder,
            'orderBulanIni' => $orderBulanIni,
            'totalUnit' => $totalUnit,
            'saldoTerbuka' => (float) $saldoTerbuka,
            'jadwalBerikutnya' => $jadwalBerikutnya,
        ])->layout('layouts.portal', ['title' => 'Ringkasan']);
    }
}
