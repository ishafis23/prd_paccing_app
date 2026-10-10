<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5b: RPM boleh desimal (contoh klien menulis "RPM : 7,3"). Kolom lama
 * decimal(8,0) membulatkan nilai; ubah ke decimal(8,1). Migrasi pembuat tabel
 * tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_unit_reports', function (Blueprint $table) {
            $table->decimal('rpm', 8, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_unit_reports', function (Blueprint $table) {
            $table->decimal('rpm', 8, 0)->nullable()->change();
        });
    }
};
