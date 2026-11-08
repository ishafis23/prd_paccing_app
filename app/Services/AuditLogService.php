<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Pencatat audit trail. Panggil di dalam transaksi yang sama dengan perubahannya. */
class AuditLogService
{
    public const PAYMENT_CATAT = 'payment.catat';

    public const PAYMENT_SINKRON_TOTAL = 'payment.sinkron_total';

    public const PAYMENT_EDIT_OMSET = 'payment.edit_omset';

    public const ORDER_KOREKSI_TOTAL = 'order.koreksi_total';

    /**
     * @param  array<string, mixed>|null  $sebelum
     * @param  array<string, mixed>|null  $sesudah
     */
    public static function catat(
        string $aksi,
        ?User $user,
        ?Model $subjek = null,
        ?int $orderId = null,
        ?array $sebelum = null,
        ?array $sesudah = null,
        ?string $keterangan = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'subjek_type' => $subjek ? $subjek::class : null,
            'subjek_id' => $subjek?->getKey(),
            'order_id' => $orderId,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'keterangan' => $keterangan,
        ]);
    }
}
