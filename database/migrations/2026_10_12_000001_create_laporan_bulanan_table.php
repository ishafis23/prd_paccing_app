<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/21 §6 (Fase 5): catatan permintaan Laporan Bulanan per Customer.
 * Satu baris = satu PDF (customer + bulan [+ cabang]). Status disimpan di
 * sini supaya tidak hilang saat halaman dimuat ulang dan terlihat walau
 * dikerjakan queue worker / mode sinkron.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laporan_bulanan')) {
            return;
        }

        Schema::create('laporan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('bulan', 7); // Y-m
            $table->foreignId('customer_address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path')->nullable(); // relatif thd disk_laporan
            $table->string('status', 20)->default('menunggu'); // menunggu | diproses | selesai | gagal
            $table->text('pesan_error')->nullable();
            $table->unsignedInteger('jumlah_order')->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_bulanan');
    }
};
