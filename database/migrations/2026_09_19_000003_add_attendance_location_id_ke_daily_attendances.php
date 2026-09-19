<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/19: lokasi GPS yang dipakai saat absen datang (mode lokasi) —
 * pasangan `attendance_code_id` yang sudah ada utk mode QR, keduanya
 * nullable & saling eksklusif tergantung mode absensi saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->foreignId('attendance_location_id')->nullable()->after('attendance_code_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_location_id');
        });
    }
};
