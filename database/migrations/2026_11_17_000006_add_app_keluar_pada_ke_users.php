<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waktu server saat teknisi logout dari app: status app langsung "keluar",
        // tidak menunggu jendela aktif 2 menit habis.
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('app_keluar_pada')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('app_keluar_pada');
        });
    }
};
