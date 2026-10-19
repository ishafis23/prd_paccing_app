<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasbon teknisi (dev-plan/28): dicatat admin, dipotong otomatis dari gaji.
 * `technician_advance_repayments` mencatat potongan yang sudah final (saat
 * gaji di-ACC) — sisa kasbon = nominal − total pelunasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('nominal', 14, 2);
            $table->decimal('cicilan_per_periode', 14, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('aktif');
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('technician_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->decimal('nominal', 14, 2);
            $table->timestamps();

            $table->unique(['technician_advance_id', 'payroll_run_id'], 'advance_run_unique');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->decimal('total_potongan_kasbon', 14, 2)->default(0)->after('total_potongan_setengah_hari');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('total_potongan_kasbon');
        });
        Schema::dropIfExists('technician_advance_repayments');
        Schema::dropIfExists('technician_advances');
    }
};
