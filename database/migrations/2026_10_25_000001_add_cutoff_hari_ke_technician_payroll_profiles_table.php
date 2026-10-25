<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_payroll_profiles', function (Blueprint $table) {
            // Tanggal terakhir periode gaji (mis. 23 → periode 24 bulan lalu s.d. 23 bulan
            // berjalan). Null = perilaku lama: periode 1 bulan dari tanggal acuan.
            $table->unsignedTinyInteger('cutoff_hari')->nullable()->after('tanggal_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('technician_payroll_profiles', function (Blueprint $table) {
            $table->dropColumn('cutoff_hari');
        });
    }
};
