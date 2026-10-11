<?php

namespace App\Livewire\Portal;

use App\Models\Invoice as InvoiceModel;
use App\Services\InvoiceService;
use App\Services\PortalAksesService;
use Livewire\Component;

/**
 * Detail invoice Portal Corporate (dev-plan/22 §4.2) — rincian tagihan +
 * daftar order yang ditagih. Kepemilikan dicek lewat PortalAksesService
 * (404 bila bukan milik customer login / status draft/batal).
 */
class InvoiceDetail extends Component
{
    public int $invoiceId;

    public function mount(InvoiceModel $invoice): void
    {
        $this->invoiceId = (int) app(PortalAksesService::class)->invoice((int) $invoice->getKey())->getKey();
    }

    public function render()
    {
        $akses = app(PortalAksesService::class);
        $service = app(InvoiceService::class);

        $invoice = $akses->invoice($this->invoiceId);
        $service->segarkanStatus($invoice);

        return view('livewire.portal.invoice-detail', [
            'invoice' => $invoice,
            'tampilan' => $service->tampilan($invoice),
            'orders' => $invoice->orders()->orderBy('tanggal_jadwal')->orderBy('id')->get(),
        ])->layout('layouts.portal', ['title' => 'Detail Invoice']);
    }
}
