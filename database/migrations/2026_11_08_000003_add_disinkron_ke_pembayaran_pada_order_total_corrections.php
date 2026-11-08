<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Penanda: koreksi total ini sudah tercermin di payments.total_tagihan (hindari dihitung ganda). */
    public function up(): void
    {
        Schema::table('order_total_corrections', function (Blueprint $table): void {
            $table->timestamp('disinkron_ke_pembayaran_pada')->nullable()->after('dikoreksi_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('order_total_corrections', function (Blueprint $table): void {
            $table->dropColumn('disinkron_ke_pembayaran_pada');
        });
    }
};
