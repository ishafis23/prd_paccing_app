<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Info penggajian dasar tiap teknisi (dev-plan/hrd/02): gaji pokok, potongan
 * BPJS TK, potongan OFF/hari, denda telat/hari, dan tanggal masuk (dasar
 * periode gaji bulanan per teknisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('tanggal_masuk');
            $table->decimal('gaji_pokok', 14, 2)->default(0);
            $table->decimal('potongan_bpjs_tk', 14, 2)->default(0);
            $table->decimal('potongan_off_per_hari', 14, 2)->default(0);
            $table->decimal('denda_telat_per_hari', 14, 2)->default(7500);
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_payroll_profiles');
    }
};
