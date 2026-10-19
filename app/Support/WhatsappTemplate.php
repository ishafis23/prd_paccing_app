<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Order;

/**
 * Penyusun teks/caption WhatsApp (dev-plan/23 §5.6) — satu tempat agar
 * pesan konsisten lintas invoice, laporan, dan tes kirim. Diadopsi dari
 * arm_app `assets/js/pages/wa-template.js` (templateWa) & caption surat di
 * `app/pages/surat/generate.php`.
 */
class WhatsappTemplate
{
    public static function rupiah(float|int|string $n): string
    {
        return 'Rp '.number_format((float) $n, 0, ',', '.');
    }

    /** Caption pengantar invoice. */
    public static function invoice(Invoice $invoice, string $namaUsaha, ?string $tautan = null): string
    {
        $invoice->loadMissing('customer');

        $teks = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
            .'Bapak/Ibu '.($invoice->customer?->nama ?? 'Pelanggan')."\n\n"
            .'Bersama pesan ini kami sampaikan invoice '.$invoice->nomor
            .' sebesar '.self::rupiah((float) $invoice->total)
            .' dari '.$namaUsaha.'.';

        if (filled($tautan)) {
            $teks .= "\n\nLihat & unduh invoice: ".$tautan;
        }

        return $teks."\n\n"
            ."Terima kasih atas kepercayaan Anda.\n\n"
            ."Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Hormat kami,\n".$namaUsaha;
    }

    /** Caption pengantar laporan pengerjaan. */
    public static function laporan(string $namaCustomer, string $namaUsaha, ?string $tautan = null): string
    {
        $teks = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
            .'Bapak/Ibu '.$namaCustomer."\n\n"
            .'Bersama pesan ini kami sampaikan laporan pengerjaan dari '.$namaUsaha
            .', rincian/foto terlampir dalam file PDF.';

        if (filled($tautan)) {
            $teks .= "\n\nLihat laporan: ".$tautan;
        }

        return $teks."\n\n"
            ."Terima kasih atas kepercayaan Anda.\n\n"
            ."Hormat kami,\n".$namaUsaha;
    }

    /** Teks pengantar resi order (dev-plan/revisiteknisi Poin 5). */
    public static function resi(Order $order, string $namaUsaha, string $tautan): string
    {
        $order->loadMissing('customer');

        $laporan = $order->workReports()->orderByDesc('waktu_selesai')->first();
        $catatanTeknisi = filled($laporan?->catatan_pengerjaan)
            ? "\n\nCatatan Teknisi: ".$laporan->catatan_pengerjaan
            : '';

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
            .'Bapak/Ibu '.($order->customer?->nama ?? 'Pelanggan')."\n\n"
            .'Terima kasih, pengerjaan '.$order->ringkasanLayanan().' telah selesai dikerjakan oleh '.$namaUsaha.'.'
            ."\n\nBukti/resi pengerjaan dapat dilihat di tautan berikut:\n".$tautan
            .$catatanTeknisi
            ."\n\nTerima kasih atas kepercayaan Anda.\n\n"
            ."Hormat kami,\n".$namaUsaha;
    }

    /** Teks bebas untuk uji coba (default bila admin tidak mengisi). */
    public static function tes(string $namaUsaha): string
    {
        return 'Pesan uji coba dari sistem '.$namaUsaha.".\n"
            .'Bila Anda menerima pesan ini, gateway WhatsApp berfungsi normal.';
    }
}
