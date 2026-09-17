<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuci/perawatan motor (dev-plan/15, Games 3 — B49): aktivitas berdiri
 * sendiri dari Order/Customer, maks 2 teknisi per baris (divalidasi di
 * service, bukan DB constraint — aturan bisnis bisa berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motor_cleanings', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('foto');
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('motor_cleaning_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motor_cleaning_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['motor_cleaning_id', 'teknisi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motor_cleaning_technicians');
        Schema::dropIfExists('motor_cleanings');
    }
};
