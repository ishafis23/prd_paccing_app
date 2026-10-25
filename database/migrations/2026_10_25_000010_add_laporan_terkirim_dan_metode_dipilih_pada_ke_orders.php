<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kapan laporan pengerjaan dikirim ke customer (otomatis via WA atau ditandai admin).
            $table->dateTime('laporan_terkirim_pada')->nullable()->after('bukti_pembayaran');
            $table->foreignId('laporan_terkirim_oleh')->nullable()->after('laporan_terkirim_pada')->constrained('users')->nullOnDelete();
            // Kapan teknisi menandai metode pembayaran (dasar tenggang sehari setoran tunai).
            $table->dateTime('metode_dipilih_pada')->nullable()->after('laporan_terkirim_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laporan_terkirim_oleh');
            $table->dropColumn(['laporan_terkirim_pada', 'metode_dipilih_pada']);
        });
    }
};
