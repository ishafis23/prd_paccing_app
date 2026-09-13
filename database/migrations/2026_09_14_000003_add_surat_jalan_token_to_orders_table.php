<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Jalan (dev-plan/12 §3.12, khusus korporat): token publik spt resi
 * (B14a) — halaman cetak/PDF daftar pekerjaan yg akan dikerjakan, dikirim
 * ke nomor order/lobby customer sebelum tim berangkat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('surat_jalan_token', 40)->nullable()->unique()->after('resi_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['surat_jalan_token']);
            $table->dropColumn('surat_jalan_token');
        });
    }
};
