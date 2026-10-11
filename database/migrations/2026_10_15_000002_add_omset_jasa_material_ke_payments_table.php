<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Omset Jasa & Omset Material di pembayaran (dev-plan/24) — acuan final split
 * jasa/material untuk laporan omset & keuangan. Nullable: data lama tetap
 * memakai perhitungan dari `order_items` (fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('omset_jasa', 14, 2)->nullable()->after('jumlah_dibayar');
            $table->decimal('omset_material', 14, 2)->nullable()->after('omset_jasa');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['omset_jasa', 'omset_material']);
        });
    }
};
