<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan masuk setengah hari bisa diatur admin per kejadian (nominal bebas).
 * NULL = pakai default lama (0,5 × potongan OFF per hari).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_early_leaves', function (Blueprint $table) {
            $table->decimal('nominal_potongan', 14, 2)->nullable()->after('alasan');
        });
    }

    public function down(): void
    {
        Schema::table('technician_early_leaves', function (Blueprint $table) {
            $table->dropColumn('nominal_potongan');
        });
    }
};
