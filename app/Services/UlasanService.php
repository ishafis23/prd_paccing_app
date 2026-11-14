<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderReview;

/** Ulasan customer atas pekerjaan (revisi 14 Nov 2026 poin 8). */
class UlasanService
{
    /** Ulasan hanya untuk order yang sudah selesai dan belum pernah diulas. */
    public function bisaDiulas(Order $order): bool
    {
        return $order->status === OrderStatus::Selesai && ! $order->review()->exists();
    }

    public function simpan(Order $order, int $rating, ?string $komentar): OrderReview
    {
        if ($order->status !== OrderStatus::Selesai) {
            throw new BusinessRuleException('Ulasan baru bisa diberikan setelah pengerjaan selesai.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new BusinessRuleException('Penilaian harus 1–5 bintang.');
        }

        if ($order->review()->exists()) {
            throw new BusinessRuleException('Ulasan untuk pekerjaan ini sudah pernah dikirim. Terima kasih!');
        }

        return OrderReview::create([
            'order_id' => $order->id,
            'teknisi_id' => $order->teknisi_id,
            'rating' => $rating,
            'komentar' => filled($komentar) ? mb_substr(trim((string) $komentar), 0, 1000) : null,
        ]);
    }

    /** @return array{rata_rata: ?float, jumlah: int} */
    public function ringkasanTeknisi(int $teknisiId): array
    {
        $q = OrderReview::query()->where('teknisi_id', $teknisiId);

        return [
            'rata_rata' => ($rata = $q->avg('rating')) === null ? null : round((float) $rata, 2),
            'jumlah' => (int) $q->count(),
        ];
    }
}
