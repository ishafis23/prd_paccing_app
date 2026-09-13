<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_reports', function (Blueprint $table) {
            $table->timestamp('diverifikasi_pada')->nullable()->after('waktu_selesai');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('diverifikasi_pada')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_reports', function (Blueprint $table) {
            $table->dropColumn(['diverifikasi_pada', 'diverifikasi_oleh']);
        });
    }
};
