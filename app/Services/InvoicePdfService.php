<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Render PDF invoice (dompdf). Dengan lampiran: satu dokumen HTML —
 * faktur di halaman pertama, lalu laporan pengerjaan tiap order (disusun
 * LaporanPengerjaanService, view laporan._dokumen dipakai ulang) —
 * dirender SEKALI, tanpa library penggabung PDF. Token publik tidak
 * pernah masuk ke PDF.
 */
class InvoicePdfService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly LaporanPengerjaanService $laporan,
    ) {}

    /**
     * @param  array<string, mixed>  $opsiOutput  diteruskan ke Dompdf::output() (mis. ['compress' => 0] utk uji)
     */
    public function render(Invoice $invoice, bool $lampiran = false, array $opsiOutput = []): string
    {
        @set_time_limit(0);
        if ((int) ini_get('memory_limit') !== -1 && $this->memoriMb() < 512) {
            @ini_set('memory_limit', '512M');
        }

        $inv = $this->invoices->tampilan($invoice);

        $orders = $lampiran
            ? $invoice->orders()->orderBy('tanggal_jadwal')->orderBy('id')->get()
            : [];

        $dokumen = $this->laporan->dokumen(
            $orders,
            $inv['customer'],
            'Faktur '.$invoice->nomor.($lampiran ? ' · dengan Lampiran Laporan Pengerjaan' : ''),
        );
        // Kop biru dipakai tiap halaman: judulnya nama usaha, bukan judul laporan.
        $dokumen['judul'] = $inv['kop']['nama'];

        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'chroot' => [base_path(), storage_path()],
        ])
            ->loadView('invoice.pdf', ['inv' => $inv, 'dokumen' => $dokumen])
            ->setPaper('a4')
            ->output($opsiOutput);
    }

    public static function namaFile(Invoice $invoice, bool $lampiran = false): string
    {
        return $invoice->nomor.($lampiran ? '-lampiran' : '').'.pdf';
    }

    private function memoriMb(): int
    {
        $nilai = trim((string) ini_get('memory_limit'));
        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024,
            'k' => intdiv($angka, 1024),
            'm' => $angka,
            default => intdiv($angka, 1024 * 1024),
        };
    }
}
