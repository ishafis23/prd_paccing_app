<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Riwayat titik GPS teknisi selama jam kerja (dikirim berkelompok dari app, termasuk hasil rekam offline). */
    public function up(): void
    {
        Schema::create('teknisi_lokasi_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('akurasi_meter')->nullable();
            $table->dateTime('direkam_pada');
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'direkam_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknisi_lokasi_logs');
    }
};
