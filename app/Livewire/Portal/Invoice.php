<?php

namespace App\Livewire\Portal;

use App\Enums\InvoiceStatus;
use App\Services\InvoiceService;
use App\Services\PortalAksesService;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman "Invoice" Portal Corporate (dev-plan/22 §4.2) — daftar tagihan
 * customer login (hanya terkirim/lunas) + saldo terbuka. Status selalu
 * disegarkan dari pembayaran terkini lewat InvoiceService.
 */
class Invoice extends Component
{
    #[Url]
    public string $status = '';

    public function render()
    {
        $akses = app(PortalAksesService::class);
        $service = app(InvoiceService::class);

        $customer = $akses->customer();

        $daftar = $akses->invoiceQuery()
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get()
            ->each(fn ($invoice) => $service->segarkanStatus($invoice));

        $totalSaldo = (float) $daftar
            ->where('status', InvoiceStatus::Terkirim)
            ->sum(fn ($invoice) => $invoice->saldo());

        if ($this->status === 'lunas') {
            $daftar = $daftar->where('status', InvoiceStatus::Lunas)->values();
        } elseif ($this->status === 'belum') {
            $daftar = $daftar->where('status', InvoiceStatus::Terkirim)->values();
        }

        return view('livewire.portal.invoice', [
            'customer' => $customer,
            'daftar' => $daftar,
            'totalSaldo' => $totalSaldo,
        ])->layout('layouts.portal', ['title' => 'Invoice']);
    }
}
