<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_layanan');
            $table->string('jenis_unit')->nullable();
            $table->string('pk')->nullable();
            $table->decimal('harga', 12, 2)->default(0);
            $table->unsignedSmallInteger('interval_bulan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_catalogs');
    }
};
