<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/27 fase 27-B: penanda device WA yang dipakai mengirim notifikasi
 * otomatis ke customer (berangkat/pembayaran). Bila tak ada yang ditandai,
 * sistem memakai device pertama yang terhubung.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wa_devices', 'notif_default')) {
            Schema::table('wa_devices', function (Blueprint $table) {
                $table->boolean('notif_default')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wa_devices', 'notif_default')) {
            Schema::table('wa_devices', function (Blueprint $table) {
                $table->dropColumn('notif_default');
            });
        }
    }
};
