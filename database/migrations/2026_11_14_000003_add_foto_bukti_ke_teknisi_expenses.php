<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            // Revisi 14 Nov 2026 poin 4: bukti foto wajib + proteksi foto kembar.
            $table->string('foto_path')->nullable();
            $table->string('foto_hash', 64)->nullable()->index();   // sha256 isi file (kembar persis)
            $table->string('foto_phash', 16)->nullable();           // hash perseptual (kembar hasil simpan ulang/kompres)
            $table->timestamp('foto_diunggah_pada')->nullable();    // timestamp server saat foto diterima
        });
    }

    public function down(): void
    {
        Schema::table('teknisi_expenses', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'foto_hash', 'foto_phash', 'foto_diunggah_pada']);
        });
    }
};
