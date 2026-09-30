<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautkan pengeluaran teknisi ke order (opsional) supaya bisa tampil
 * di tab Pengeluaran pada detail order bersama pengeluaran admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('teknisi_id')
                ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
