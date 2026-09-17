<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi kantor teknisi via QR (dev-plan/15, B39-B41): kode/QR yang
 * ditempel fisik di kantor, discan HP teknisi untuk absen datang/pulang.
 * Hanya 1 kode `aktif` yang berlaku di satu waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_codes', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('status', 20)->default('aktif');
            $table->string('lokasi')->nullable();
            $table->dateTime('berlaku_sampai')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_codes');
    }
};
