<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Idempotensi pengiriman dari antrean offline app (kirim ulang tidak menggandakan). */
    public function up(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable()->after('id');
            $table->unique(['teknisi_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table): void {
            $table->dropUnique(['teknisi_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
