<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotifikasiWaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Revisi 14 Nov 2026 poin 18: kirim otomatis laporan pengerjaan + invoice/kuitansi ke WA
 * customer setelah order selesai atau lunas. Idempoten (dokumen yang sudah sukses dilewati),
 * jadi aman dipicu berulang dari beberapa titik (laporan selesai, pembayaran lunas).
 * Kegagalan hanya dicatat; tidak boleh menggagalkan aksi teknisi/admin yang memicunya.
 */
class KirimLaporanOtomatisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $orderId) {}

    public function handle(NotifikasiWaService $notif): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order === null) {
            return;
        }

        try {
            $hasil = $notif->kirimOtomatisSelesai($order);

            Log::info('Kirim otomatis laporan order selesai', ['order_id' => $this->orderId] + $hasil);
        } catch (Throwable $e) {
            Log::warning('Kirim otomatis laporan order selesai gagal', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Pemicu: hanya antre bila fitur dinyalakan. */
    public static function antre(Order $order): void
    {
        if (config('wa.notif_otomatis_selesai', false)) {
            self::dispatch($order->id)->afterCommit();
        }
    }
}
