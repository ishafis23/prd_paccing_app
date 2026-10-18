<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis Layanan tambahan yang berupa misi/operasional (cari part, antar
 * barang, dll.) — tampil di jadwal teknisi tapi TIDAK masuk omset/unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->boolean('bukan_omset')->default(false)->after('komponen');
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('bukan_omset');
        });
    }
};
