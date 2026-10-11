<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal Corporate Customer (dev-plan/22, Plan A) — 1 akun login per
 * customer, tetap di tabel `customers` (bukan tabel PIC terpisah).
 * Kolom `username` baru (nullable, unik) supaya PIC bisa login pakai
 * username ATAU email. `password` sudah ada sejak
 * 2026_09_14_000008_add_password_ke_customers_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
