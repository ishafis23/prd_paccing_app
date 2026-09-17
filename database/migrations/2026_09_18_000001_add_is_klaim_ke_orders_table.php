<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prasyarat Games 2/4/5/6 (dev-plan/15, B50 — dikonfirmasi Ranto: 1
 * kunjungan/order tidak pernah campur klaim & bayar): order klaim/garansi
 * (tidak ditagih) dikecualikan total dari hitungan titik/unit/omset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_klaim')->default(false)->after('catatan_admin');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_klaim');
        });
    }
};
