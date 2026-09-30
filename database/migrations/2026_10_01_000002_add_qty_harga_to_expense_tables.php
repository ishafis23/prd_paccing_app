<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeluaran kini mencatat qty & harga satuan; kolom nominal menjadi total
 * (otomatis qty × harga, tapi tetap bisa dikoreksi manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('qty')->nullable()->after('nominal');
            $table->decimal('harga', 12, 2)->nullable()->after('qty');
        });

        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->unsignedInteger('qty')->nullable()->after('nominal');
            $table->unsignedInteger('harga')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['qty', 'harga']);
        });

        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->dropColumn(['qty', 'harga']);
        });
    }
};
