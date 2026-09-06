<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // B14a: token acak utk halaman resi publik (tanpa login).
            $table->string('resi_token', 40)->nullable()->unique()->after('metode_dipilih');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['resi_token']);
            $table->dropColumn('resi_token');
        });
    }
};
