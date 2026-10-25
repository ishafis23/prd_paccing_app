<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pelunasan kasbon manual: Admin menandai kasbon sudah dibayar/dipotong di luar
 * perhitungan gaji, jadi `payroll_run_id` boleh kosong. Unique index
 * `advance_run_unique` tetap — MySQL & SQLite mengizinkan banyak NULL di unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_advance_repayments', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')->nullable()->change();
            $table->string('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technician_advance_repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh');
            $table->dropColumn('catatan');
        });

        // NOT NULL hanya dikembalikan bila tidak ada pelunasan manual (baris NULL);
        // kalau ada, kolom dibiarkan nullable supaya data tidak rusak.
        if (! DB::table('technician_advance_repayments')->whereNull('payroll_run_id')->exists()) {
            Schema::table('technician_advance_repayments', function (Blueprint $table) {
                $table->foreignId('payroll_run_id')->nullable(false)->change();
            });
        }
    }
};
