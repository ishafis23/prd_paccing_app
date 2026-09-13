<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order jadi bisa punya beberapa baris layanan (order_items), bukan cuma
 * satu service_catalog_id tunggal — dasar utk alur "Ada Perbaikan": admin
 * menambah baris baru (mis. ganti sparepart) ke order yg sedang jalan
 * tanpa mengubah baris layanan awal. Lihat dev-plan/13 §1.
 *
 * orders.service_catalog_id/jumlah_unit TETAP ada (kompatibilitas lama);
 * migrasi ini juga backfill 1 order_item per order existing dari kolom
 * itu, supaya Order::total() (skrg dihitung dari sum order_items) tidak
 * berubah nilainya utk data yg sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama_layanan');
            $table->string('kategori')->nullable();
            $table->decimal('harga', 12, 2);
            $table->unsignedInteger('jumlah')->default(1);
            $table->text('catatan')->nullable();
            $table->foreignId('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $orders = DB::table('orders')->select('id', 'service_catalog_id', 'jumlah_unit', 'created_at', 'updated_at')->orderBy('id')->get();
        $katalog = DB::table('service_catalogs')->get()->keyBy('id');

        foreach ($orders->chunk(200) as $chunk) {
            $baris = [];

            foreach ($chunk as $order) {
                $catalog = $katalog->get($order->service_catalog_id);

                $baris[] = [
                    'order_id' => $order->id,
                    'service_catalog_id' => $order->service_catalog_id,
                    'nama_layanan' => $catalog->jenis_layanan ?? 'Layanan',
                    'kategori' => $catalog->jenis_layanan ?? null,
                    'harga' => $catalog->harga ?? 0,
                    'jumlah' => $order->jumlah_unit,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            }

            if ($baris !== []) {
                DB::table('order_items')->insert($baris);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
