<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi kantor harian teknisi (dev-plan/15, B39): 1 baris per
 * teknisi/tanggal, terpisah dari `attendances` (absen per kunjungan order
 * di lokasi customer — lihat dev-plan/15 §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_code_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tanggal');
            $table->dateTime('jam_datang')->nullable();
            $table->string('foto_datang')->nullable();
            $table->string('status_datang', 30)->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->string('foto_pulang')->nullable();
            $table->boolean('dikecualikan_denda')->default(false);
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};
