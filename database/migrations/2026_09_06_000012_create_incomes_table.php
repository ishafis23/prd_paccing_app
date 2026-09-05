<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kategori');
            $table->decimal('nominal', 12, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['kategori', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
