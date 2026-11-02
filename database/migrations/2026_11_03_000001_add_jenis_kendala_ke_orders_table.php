<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // 'besok' = pending dikerjakan besok/hari lain (status terkendala, admin jadwalkan ulang);
            // 'sore'  = pending tapi bisa dikerjakan sore (status tetap aktif, titik berikutnya terbuka).
            $table->string('jenis_kendala', 10)->nullable()->after('alasan_kendala');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('jenis_kendala');
        });
    }
};
