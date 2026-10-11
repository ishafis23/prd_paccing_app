<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal hari OFF teknisi (dev-plan/hrd/02) — admin menandai tanggal libur.
 * Jatah 5 hari/periode (seragam); kelebihan dipotong saat penggajian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_off_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_off_days');
    }
};
