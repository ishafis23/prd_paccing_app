<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data slot jam kunjungan baku (dev-plan/18) — mis. "Titik 1" =
 * 08:15, "Titik 2" = 09:30, dst. Admin kelola sekali, dipakai berulang tiap
 * hari saat bikin order lewat wizard Create Order (gantikan input jam
 * manual). `urutan` dipakai utk drag-reorder di admin (pola HeroSlideResource).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('titiks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->time('jam');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titiks');
    }
};
