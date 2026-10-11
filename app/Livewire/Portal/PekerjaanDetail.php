<?php

namespace App\Livewire\Portal;

use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\LaporanPengerjaanService;
use App\Services\PortalAksesService;
use Livewire\Component;

/**
 * Detail satu pekerjaan (order) Portal Corporate (dev-plan/22 §4.2) — info
 * order + laporan foto/keterangan per unit. Kepemilikan dicek lewat
 * PortalAksesService (404 bila bukan milik customer login / order batal).
 */
class PekerjaanDetail extends Component
{
    public int $orderId;

    public function mount(Order $order): void
    {
        $this->orderId = (int) app(PortalAksesService::class)->order((int) $order->getKey())->getKey();
    }

    public function render()
    {
        $akses = app(PortalAksesService::class);
        $laporan = app(LaporanPengerjaanService::class);

        $order = $akses->order($this->orderId)->load([
            'customer',
            'customerAddress',
            'serviceCatalog',
            'orderItems',
            'teknisi',
            'timTeknisi',
        ]);

        $invoice = app(InvoiceService::class)->invoiceAktifOrder($order);

        return view('livewire.portal.pekerjaan-detail', [
            'order' => $order,
            'dokumen' => $laporan->untukOrder($order),
            'invoice' => $invoice,
        ])->layout('layouts.portal', ['title' => 'Detail Pekerjaan']);
    }
}
