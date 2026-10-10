<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2: cara katalog menentukan komponen omset baris order —
 * `otomatis` (ikut IncomeCategory::untukLayanan), `jasa`, atau `material`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->string('mode_omset')->default('otomatis')->after('harga');
        });
    }

    public function down(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->dropColumn('mode_omset');
        });
    }
};
