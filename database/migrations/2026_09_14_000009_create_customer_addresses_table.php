<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-alamat per customer (dev-plan/14) — 1 customer bisa punya banyak
 * alamat (rumah 1, rumah 2, usaha, dst); setiap alamat punya koordinat
 * sendiri (maps/lat/lng) karena teknisi menuju lokasi alamat terpilih saat
 * order. `customer_ac_units` & `orders` akan menunjuk ke alamat ini lewat
 * migrasi berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('nama_lokasi')->nullable();
            $table->text('alamat');
            $table->text('maps_link')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_utama')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};