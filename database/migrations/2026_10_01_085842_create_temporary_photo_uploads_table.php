<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temporary_photo_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->string('field_name'); // fotoSebelum, fotoSesudah, fotoKategori.1.slot1, dll
            $table->string('file_path'); // Path file yang sudah diupload
            $table->string('file_name');
            $table->bigInteger('file_size');
            $table->string('mime_type');
            $table->timestamps();
            $table->softDeletes(); // Untuk tracking deleted temporary files

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['user_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_photo_uploads');
    }
};
