<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: foto laporan bisa ditautkan ke unit tertentu. `unit_no` = nomor
 * unit DI DALAM baris layanan (1..jumlah), default 1 — foto lama otomatis
 * unit 1 (kolom baru memakai default, jadi baris lama langsung terisi 1).
 * `order_unit_report_id` nullable: foto lama / order tanpa data unit tetap
 * valid tanpa tautan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_report_photos', 'unit_no')) {
            Schema::table('work_report_photos', function (Blueprint $table) {
                $table->unsignedInteger('unit_no')->default(1)->after('order_item_id');
            });
        }

        if (! Schema::hasColumn('work_report_photos', 'order_unit_report_id')) {
            Schema::table('work_report_photos', function (Blueprint $table) {
                $table->foreignId('order_unit_report_id')->nullable()->after('unit_no')
                    ->constrained('order_unit_reports')->nullOnDelete();
            });
        }

        // Backfill eksplisit (idempotent) untuk jaga-jaga nilai NULL.
        \Illuminate\Support\Facades\DB::table('work_report_photos')->whereNull('unit_no')->update(['unit_no' => 1]);
    }

    public function down(): void
    {
        Schema::table('work_report_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_unit_report_id');
            $table->dropColumn('unit_no');
        });
    }
};
