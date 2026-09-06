<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // B13b: metode yang dipilih customer, ditandai teknisi saat
            // menampilkan opsi bayar; info utk Admin. Pencatatan resmi tetap
            // di tabel payments.
            $table->string('metode_dipilih')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('metode_dipilih');
        });
    }
};
