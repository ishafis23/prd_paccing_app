<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto laporan per kategori (dev-plan/13 §3): tiap order_item (bukan tiap
 * order) punya slot foto sendiri sesuai kategori (ServiceType), mis. Cuci AC
 * -> outdoor_proses, indoor_proses, dst (lihat App\Support\FotoLaporanSlot).
 * WorkReport.foto_sebelum/foto_sesudah (kolom lama) tetap dipertahankan utk
 * data lama; laporan baru pakai tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->string('path');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_report_photos');
    }
};
