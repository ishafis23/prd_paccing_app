<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner tim/pendamping pada pengeluaran harian: satu catatan, dihitung untuk
 * dua teknisi (syarat "pengeluaran harian wajib diisi" terpenuhi untuk keduanya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('teknisi_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
