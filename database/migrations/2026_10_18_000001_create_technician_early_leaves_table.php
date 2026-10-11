<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan pulang cepat teknisi (dev-plan/hrd/02): teknisi mengisi tanggal,
 * jam pulang, & alasan; HRD/Admin memverifikasi. Yang disetujui dianggap
 * setengah hari dan dipotong saat penggajian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_early_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_pulang');
            $table->text('alasan')->nullable();
            $table->string('status')->default('menunggu');
            $table->text('catatan_hrd')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_early_leaves');
    }
};
