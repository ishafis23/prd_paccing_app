<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_reminders', function (Blueprint $table) {
            $table->string('sumber_interval', 20)->default('default')->after('interval_bulan'); // default | teknisi
            $table->unsignedTinyInteger('status_kirim')->default(0)->after('status_notice');    // 0 menunggu, 1 terkirim, 2 gagal
            $table->unsignedSmallInteger('percobaan')->default(0)->after('status_kirim');
            $table->unsignedSmallInteger('jumlah_terkirim')->default(0)->after('percobaan');
            $table->timestamp('terakhir_dicoba_at')->nullable()->after('jumlah_terkirim');
            $table->timestamp('terkirim_at')->nullable()->after('terakhir_dicoba_at');
            $table->string('error_terakhir', 255)->nullable()->after('terkirim_at');
            $table->string('tahap', 20)->default('awal')->after('error_terakhir');                // awal | bulanan
        });
    }

    public function down(): void
    {
        Schema::table('service_reminders', function (Blueprint $table) {
            $table->dropColumn(['sumber_interval', 'status_kirim', 'percobaan', 'jumlah_terkirim', 'terakhir_dicoba_at', 'terkirim_at', 'error_terakhir', 'tahap']);
        });
    }
};
