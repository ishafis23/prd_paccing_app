<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 17 Nov 2026 poin 29: bonus / potongan lain yang diinput admin
        // (boleh berkali-kali, disertai keterangan & catatan). Disimpan terpisah
        // dari payroll_run_items karena item dibangun ulang tiap Hitung Ulang.
        Schema::create('payroll_run_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('jenis', 10); // bonus | potongan
            $table->string('keterangan');
            $table->date('tanggal')->nullable();
            $table->decimal('nominal', 14, 2);
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->decimal('total_bonus_lain', 14, 2)->default(0)->after('bonus_mingguan');
            $table->decimal('total_potongan_lain', 14, 2)->default(0)->after('total_potongan_kasbon');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn(['total_bonus_lain', 'total_potongan_lain']);
        });

        Schema::dropIfExists('payroll_run_adjustments');
    }
};
