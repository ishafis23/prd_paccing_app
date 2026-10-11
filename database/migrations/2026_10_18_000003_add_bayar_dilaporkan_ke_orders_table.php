<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lapor bayar oleh teknisi (dev-plan/revisiteknisi Poin 6): teknisi menandai
 * "customer sudah bayar" di portalnya; admin melihatnya sebagai penanda untuk
 * mencatat pembayaran resmi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('bayar_dilaporkan_teknisi')->default(false)->after('metode_dipilih');
            $table->timestamp('bayar_dilaporkan_pada')->nullable()->after('bayar_dilaporkan_teknisi');
            $table->unsignedBigInteger('bayar_dilaporkan_oleh')->nullable()->after('bayar_dilaporkan_pada');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bayar_dilaporkan_teknisi', 'bayar_dilaporkan_pada', 'bayar_dilaporkan_oleh']);
        });
    }
};
