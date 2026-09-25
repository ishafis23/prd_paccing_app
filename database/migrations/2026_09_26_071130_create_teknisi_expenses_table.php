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
        Schema::create('teknisi_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teknisi_id')->constrained('users');
            $table->date('tanggal_input');
            $table->string('kategori'); // bensin, makan, lainnya
            $table->integer('nominal');
            $table->text('keterangan')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('catatan_approval')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teknisi_expenses');
    }
};
