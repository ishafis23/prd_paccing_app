<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/19 B71: 1 pengaturan mode absensi, berlaku semua teknisi.
 * Default 'qr' — hosting yang sudah pakai QR tidak berubah perilakunya
 * tanpa admin ganti pengaturan secara eksplisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->string('mode_absensi', 20)->default('qr')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('mode_absensi');
        });
    }
};
