<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan order ke slot Titik yg dipilih (dev-plan/18) — sumber `jam_jadwal`
 * sekarang berasal dari `titik->jam`, kolom ini murni utk tracking/filter
 * "order mana saja ada di titik X" di daftar order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('titik_id')->nullable()->after('jam_jadwal')
                ->constrained('titiks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('titik_id');
        });
    }
};
