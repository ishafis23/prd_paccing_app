<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Pilihan customer (ditanyakan teknisi): cuci ulang tiap berapa bulan. Null = default.
            $table->unsignedSmallInteger('interval_servis_bulan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('interval_servis_bulan');
        });
    }
};
