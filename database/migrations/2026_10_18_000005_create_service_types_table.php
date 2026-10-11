<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis Layanan TAMBAHAN (dev-plan/26) — melengkapi enum `ServiceType` (7 jenis
 * bawaan, read-only). Admin menambah jenis baru di sini; masuk opsi select.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('label');
            $table->string('komponen')->default('jasa'); // jasa | material
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
