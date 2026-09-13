<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link Order/order_items ke Unit AC spesifik (dev-plan/12 §3.10 lanjutan)
 * — supaya laporan pekerjaan bisa menunjukkan unit AC mana yang dikerjakan
 * kalau customer (rumahan maupun company) punya lebih dari satu unit.
 * Nullable & opsional — customer lama tanpa data Unit AC tetap jalan spt
 * biasa (jumlah_unit tetap dipakai sbg pengali harga, bukan diganti).
 *
 * orders.customer_ac_unit_id = unit utk baris order_item pertama (otomatis
 * dibuat via Order::booted(), sama pola dgn service_catalog_id/jumlah_unit).
 * order_items.customer_ac_unit_id = unit per baris (termasuk baris
 * tambahan lewat "Tambah Layanan"/"Setujui Perbaikan").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_ac_unit_id')->nullable()->after('service_catalog_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('customer_ac_unit_id')->nullable()->after('service_catalog_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_ac_unit_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_ac_unit_id');
        });
    }
};
