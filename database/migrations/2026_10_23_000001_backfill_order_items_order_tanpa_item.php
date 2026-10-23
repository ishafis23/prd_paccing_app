<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Order lama yang tidak punya baris `order_items` sama sekali membuat tab
 * "Rincian Layanan" kosong (dan invoice/omset tidak punya acuan), padahal
 * kolom daftar order masih menampilkan layanan dari orders.service_catalog_id
 * + orders.jumlah_unit. Migrasi ini membuat ulang SATU baris item dari kolom
 * order tersebut, sama seperti Order::booted() saat order dibuat.
 *
 * Idempotent: hanya menyentuh order yang benar-benar belum punya item.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id');
            })
            ->chunkById(200, function ($orders): void {
                $katalog = DB::table('service_catalogs')
                    ->whereIn('id', $orders->pluck('service_catalog_id')->filter()->all())
                    ->get()
                    ->keyBy('id');

                $baris = [];

                foreach ($orders as $order) {
                    $catalog = $katalog->get($order->service_catalog_id);
                    $kategori = $catalog->jenis_layanan ?? null;

                    $baris[] = [
                        'order_id' => $order->id,
                        'service_catalog_id' => $order->service_catalog_id,
                        'customer_ac_unit_id' => $order->customer_ac_unit_id,
                        'nama_layanan' => $catalog !== null
                            ? str($kategori)->headline()->toString()
                            : 'Layanan',
                        'kategori' => $kategori,
                        'komponen' => in_array($kategori, ['cuci_ac', 'service_ac'], true) ? 'jasa' : 'material',
                        'penyesuaian' => false,
                        'harga' => $catalog->harga ?? 0,
                        'jumlah' => max(1, (int) $order->jumlah_unit),
                        'dibatalkan' => false,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ];
                }

                if ($baris !== []) {
                    DB::table('order_items')->insert($baris);
                }
            });
    }

    public function down(): void
    {
        // Data-only backfill: baris item lama tidak bisa dibedakan dari baris
        // yang memang dibuat manual, jadi tidak dihapus saat rollback.
    }
};
