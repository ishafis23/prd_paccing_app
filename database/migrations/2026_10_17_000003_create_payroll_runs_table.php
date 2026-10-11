<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu hitungan gaji = 1 teknisi + 1 periode (dev-plan/hrd/02).
 * Gaji bersih = Gaji Pokok + Bonus Bulanan + Bonus Umum + Bonus Mingguan
 *               − Bonus Mingguan (sudah dibayar) − Denda Telat − Potongan OFF − BPJS TK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->decimal('gaji_pokok', 14, 2)->default(0);
            $table->decimal('bonus_bulanan', 14, 2)->default(0);   // Games 1,2,3,7 + Lainnya
            $table->decimal('bonus_umum', 14, 2)->default(0);      // input admin/Owner
            $table->decimal('bonus_mingguan', 14, 2)->default(0);  // Games 4,5,6 (info + pengurang)
            $table->unsignedInteger('jumlah_hari_telat')->default(0);
            $table->decimal('total_denda_telat', 14, 2)->default(0);
            $table->unsignedInteger('jumlah_hari_off')->default(0);
            $table->decimal('total_potongan_off', 14, 2)->default(0);
            $table->decimal('potongan_bpjs_tk', 14, 2)->default(0);
            $table->decimal('gaji_bersih', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('catatan')->nullable();
            $table->foreignId('dihitung_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamp('dibayar_pada')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'periode_mulai', 'periode_selesai']);
        });

        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('jenis');                 // bonus | potongan
            $table->string('kategori');              // games1..7, bonus_umum, telat, off, bpjs, dibayar_mingguan
            $table->string('label');
            $table->decimal('nominal', 14, 2);
            $table->string('referensi')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');
    }
};
