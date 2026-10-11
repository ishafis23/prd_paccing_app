<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\LaporanPdfService;
use App\Services\LaporanPengerjaanService;
use App\Services\PortalAksesService;
use Illuminate\Support\Str;

/**
 * Berkas Portal Corporate (dev-plan/22 §4.1) — unduh PDF laporan pengerjaan
 * & invoice. Kepemilikan dicek lewat PortalAksesService, sehingga order/invoice
 * milik customer lain menghasilkan 404. Foto tidak pernah disajikan lewat
 * /storage/ — dilekatkan sebagai data URI di halaman detail.
 */
class BerkasController extends Controller
{
    public function __construct(
        private readonly PortalAksesService $akses,
        private readonly LaporanPengerjaanService $laporan,
        private readonly LaporanPdfService $pdfLaporan,
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdfInvoice,
    ) {}

    public function laporan(Order $order)
    {
        $order = $this->akses->order((int) $order->getKey());

        $dokumen = $this->laporan->dokumenOrder($order);
        $nama = 'Laporan-'.Str::slug($order->customer?->nama ?? 'customer')
            .'-'.($order->tanggal_jadwal?->format('Ymd') ?? 'order').'-'.$order->id.'.pdf';

        return response($this->pdfLaporan->render($dokumen), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    public function invoice(Invoice $invoice)
    {
        $invoice = $this->akses->invoice((int) $invoice->getKey());
        $this->invoices->segarkanStatus($invoice);

        $lampiran = request()->boolean('lampiran');

        return response($this->pdfInvoice->render($invoice, $lampiran), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.InvoicePdfService::namaFile($invoice, $lampiran).'"',
        ]);
    }
}
