<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 14 Nov 2026 poin 13: dokumentasi video pengerjaan (mp4) yang ditambah teknisi sendiri.
        Schema::create('order_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('nama_asli')->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('ukuran')->default(0); // byte
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_videos');
    }
};
