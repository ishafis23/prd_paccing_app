<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom pulang cepat / setengah hari pada penggajian (dev-plan/hrd/02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_setengah_hari')->default(0)->after('jumlah_hari_off');
            $table->decimal('total_potongan_setengah_hari', 14, 2)->default(0)->after('total_potongan_off');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['jumlah_setengah_hari', 'total_potongan_setengah_hari']);
        });
    }
};
