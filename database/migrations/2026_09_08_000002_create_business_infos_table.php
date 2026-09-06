<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_infos', function (Blueprint $table) {
            $table->id();
            $table->string('nama_usaha');
            $table->text('alamat')->nullable();
            $table->string('kontak_wa', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('nama_pemilik', 255)->nullable();
            $table->string('logo_path')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_infos');
    }
};
