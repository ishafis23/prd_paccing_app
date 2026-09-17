<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger generik bonus/denda teknisi (dev-plan/15, §4 revisi setelah §1c):
 * 1 baris = 1 kejadian (Games 1-6, denda telat, dll) per teknisi per
 * tanggal per kategori. Dipakai gantikan kolom nominal tetap di
 * `daily_attendances` supaya nambah kategori baru tidak perlu migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('kategori', 40);
            $table->string('tipe', 10);
            $table->decimal('nominal', 12, 2);
            $table->string('foto_bukti')->nullable();
            $table->string('sumber', 20)->default('otomatis');
            $table->text('referensi')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->string('status_verifikasi', 20)->default('menunggu');
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_incentives');
    }
};
