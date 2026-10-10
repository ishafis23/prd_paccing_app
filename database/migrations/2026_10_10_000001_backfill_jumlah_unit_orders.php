<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1 (dev-plan/21 §2): orders.jumlah_unit selalu 1 pada order multi-item
 * (hard-code di OrderService) sehingga tampilan "1 unit" salah. Isi ulang dari
 * Σ order_items.jumlah (baris tidak dibatalkan). Idempotent; tidak menyentuh
 * harga/total apa pun. Order tanpa item aktif dibiarkan apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders): void {
            foreach ($orders as $order) {
                $jumlah = (int) DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->where('dibatalkan', false)
                    ->sum('jumlah');

                if ($jumlah > 0 && (int) $order->jumlah_unit !== $jumlah) {
                    DB::table('orders')->where('id', $order->id)->update(['jumlah_unit' => $jumlah]);
                }
            }
        });
    }

    public function down(): void
    {
        // Data-only backfill: nilai lama (selalu 1 / input awal) tidak bisa direkonstruksi.
    }
};
