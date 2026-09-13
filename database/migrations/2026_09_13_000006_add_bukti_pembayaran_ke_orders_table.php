<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('jenis_pelanggan')->nullable()->after('alamat_pengerjaan');
            $table->string('bukti_pembayaran')->nullable()->after('metode_dipilih');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['jenis_pelanggan', 'bukti_pembayaran']);
        });
    }
};
