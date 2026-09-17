<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi kantor teknisi (dev-plan/15, B42): 1 baris pengaturan (pola sama
 * seperti `business_infos`) — seluruh ambang jam & nominal skema "Games
 * 1-7" (dev-plan/15 §1a/§1b), admin-editable tanpa deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();

            // Games 1 (hadir) + denda telat + toleransi lembur malam sebelumnya.
            $table->time('jam_games1_batas')->default('07:35:00');
            $table->decimal('nominal_games1', 12, 2)->default(7500);
            $table->time('jam_normal_selesai')->default('08:05:00');
            $table->decimal('nominal_denda_telat', 12, 2)->default(7500);
            $table->time('jam_toleransi_lembur_mulai')->default('20:00:00');
            $table->time('jam_toleransi_batas_denda')->default('10:00:00');

            // Games 2 (titik pertama).
            $table->time('jam_games2_batas')->default('08:30:00');
            $table->decimal('nominal_games2', 12, 2)->default(7500);

            // Games 3 (cuci motor).
            $table->decimal('nominal_games3', 12, 2)->default(3000);

            // Games 4 (kepulangan: titik + jam).
            $table->time('jam_games4_batas')->default('17:45:00');
            $table->unsignedInteger('minimal_titik_berdua')->default(9);
            $table->unsignedInteger('minimal_titik_sendiri')->default(5);
            $table->decimal('nominal_games4_berdua', 12, 2)->default(15000);
            $table->decimal('nominal_games4_sendiri', 12, 2)->default(25000);

            // Games 5 (omset tim).
            $table->decimal('omset_games5_minimal', 14, 2)->default(850000);
            $table->decimal('nominal_games5_berdua', 12, 2)->default(40000);
            $table->decimal('nominal_games5_sendiri', 12, 2)->default(100000);

            // Games 6 (unit selesai).
            $table->unsignedInteger('unit_games6_berdua')->default(12);
            $table->unsignedInteger('unit_games6_sendiri')->default(6);
            $table->decimal('nominal_games6', 12, 2)->default(50000);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
