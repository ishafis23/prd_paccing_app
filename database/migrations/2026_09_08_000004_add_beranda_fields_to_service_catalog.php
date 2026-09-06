<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('harga');
            $table->string('gambar')->nullable()->after('deskripsi');
            $table->boolean('tampil_beranda')->default(false)->after('gambar');
            $table->unsignedSmallInteger('urutan_beranda')->default(0)->after('tampil_beranda');
        });
    }

    public function down(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'gambar', 'tampil_beranda', 'urutan_beranda']);
        });
    }
};
