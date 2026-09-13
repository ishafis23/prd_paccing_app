<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak tim baku yg dipakai saat assign (dev-plan/12 §3.13) — murni
 * informasional/traceability. Sumber kebenaran siapa yg benar-benar
 * bertugas tetap teknisi_id (PIC) & order_technicians (anggota), sama
 * spt assign teknisi satu-satu — supaya order tetap konsisten walau
 * kelak anggota tim di order itu diubah manual (Ganti PIC/Tambah
 * Anggota Tim) tanpa perlu ganti tim di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('customer_ac_unit_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
