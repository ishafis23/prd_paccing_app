<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Support\Url;
use Illuminate\Http\Request;

/**
 * Unduh PDF invoice untuk admin (Owner/Admin/Finance, login) + halaman publik
 * read-only bertoken (pola resi_token/surat_jalan_token). Invoice batal dan
 * draft tidak pernah tampil/diunduh lewat tautan publik; invoice batal juga
 * tidak bisa diunduh admin sebagai tagihan.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function pdfAdmin(Request $request, Invoice $invoice)
    {
        abort_unless(InvoiceService::boleh($request->user()), 403);
        abort_if($invoice->batal(), 404, 'Invoice dibatalkan.');

        $this->invoices->segarkanStatus($invoice);

        return $this->kirimPdf($invoice, $request->boolean('lampiran'));
    }

    public function publik(Invoice $invoice, string $token)
    {
        $this->pastikanSah($invoice, $token);
        $this->invoices->segarkanStatus($invoice);

        return view('invoice.publik', [
            'inv' => $this->invoices->tampilan($invoice),
            'urlPdf' => Url::absolute('invoice.publik.pdf', ['invoice' => $invoice->id, 'token' => $token]),
            'urlPdfLampiran' => Url::absolute('invoice.publik.pdf', ['invoice' => $invoice->id, 'token' => $token]).'?lampiran=1',
        ]);
    }

    public function publikPdf(Request $request, Invoice $invoice, string $token)
    {
        $this->pastikanSah($invoice, $token);
        $this->invoices->segarkanStatus($invoice);

        return $this->kirimPdf($invoice, $request->boolean('lampiran'));
    }

    private function pastikanSah(Invoice $invoice, string $token): void
    {
        $sah = $invoice->token !== null
            && hash_equals((string) $invoice->token, $token)
            && in_array($invoice->status, [InvoiceStatus::Terkirim, InvoiceStatus::Lunas], true);

        abort_unless($sah, 404);
    }

    private function kirimPdf(Invoice $invoice, bool $lampiran)
    {
        return response($this->pdf->render($invoice, $lampiran), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.InvoicePdfService::namaFile($invoice, $lampiran).'"',
        ]);
    }
}
