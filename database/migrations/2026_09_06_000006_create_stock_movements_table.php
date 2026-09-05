<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('jenis')->default('masuk');
            $table->integer('jumlah');
            $table->string('referensi')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users');
            $table->date('tanggal');
            $table->timestamps();
            $table->index(['stock_item_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
