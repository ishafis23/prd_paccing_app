<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 255)->nullable();
            $table->text('subjudul')->nullable();
            $table->string('gambar');
            $table->string('tombol_teks', 100)->nullable();
            $table->string('tombol_url', 500)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('beranda_settings', function (Blueprint $table) {
            $table->id();
            $table->text('maps_embed')->nullable();
            $table->string('jam_operasional', 255)->nullable();
            $table->string('sosmed_instagram', 255)->nullable();
            $table->string('sosmed_facebook', 255)->nullable();
            $table->boolean('tampil_layanan')->default(true);
            $table->boolean('tampil_cara_kerja')->default(true);
            $table->boolean('tampil_area')->default(true);
            $table->boolean('tampil_peta')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beranda_settings');
        Schema::dropIfExists('hero_slides');
    }
};
