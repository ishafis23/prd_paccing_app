<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo terstruktur per layanan & unit (dev-plan/teknisi/fase03):
 * Lokasi: 1 foto, Cuci AC: 5 foto per unit (repeatable), Service AC: 3 foto per unit (repeatable).
 * Support untuk multiple units dengan unit_number field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Photo categorization
            $table->enum('type', ['lokasi', 'cuci', 'service'])->comment('Tipe foto: lokasi, cuci, service');
            $table->unsignedInteger('unit_number')->default(1)->comment('Unit number untuk multiple units');
            $table->string('photo_position')->comment('indoor, outdoor, area_indoor, area_outdoor, suhu, kendala, pengerjaan, selesai');

            // File info
            $table->string('file_path')->comment('Storage path ke foto');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type')->nullable()->default('image/jpeg');

            // Status & metadata
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('type');
            $table->index('unit_number');
            $table->index('status');
            $table->index(['order_id', 'type', 'unit_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_photos');
    }
};
