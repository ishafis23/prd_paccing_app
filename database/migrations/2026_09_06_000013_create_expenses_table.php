<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('kategori');
            $table->decimal('nominal', 12, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->string('bukti')->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users');
            $table->timestamps();
            $table->index(['kategori', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
