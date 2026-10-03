<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi costumer: satu baris layanan (unit) bisa batal di tengah jalan —
 * mis. customer minta cuci 2 unit lalu salah satunya tidak jadi. Baris yang
 * batal tidak dihitung ke total tagihan (Order::total()) dan tidak menuntut
 * foto/dokumentasi teknisi, tapi tetap tersimpan sebagai jejak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('dibatalkan')->default(false)->after('jumlah');
            $table->timestamp('dibatalkan_pada')->nullable()->after('dibatalkan');
            $table->string('alasan_batal')->nullable()->after('dibatalkan_pada');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan', 'dibatalkan_pada', 'alasan_batal']);
        });
    }
};
