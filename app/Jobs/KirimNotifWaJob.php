<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\NotifikasiWaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WA "teknisi berangkat" ke customer (dev-plan/27 fase 27-B),
 * dijalankan di antrean agar aksi berangkat tetap cepat. Kegagalan NOTIF
 * tidak boleh menggagalkan aksi berangkat (order tetap menuju_lokasi).
 */
class KirimNotifWaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $orderId,
        public readonly int $teknisiId,
    ) {}

    public function handle(NotifikasiWaService $notif): void
    {
        $order = Order::query()->find($this->orderId);
        $teknisi = User::query()->find($this->teknisiId);

        if ($order === null || $teknisi === null) {
            return;
        }

        try {
            $notif->kirimBerangkat($order, $teknisi);
        } catch (Throwable $e) {
            // Best-effort: catat, jangan gagalkan apa pun.
            Log::warning('Notifikasi WA berangkat gagal', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
