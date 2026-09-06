<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_channels', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jenis'); // qris | bank (extensible ke ewallet)
            $table->string('atas_nama')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('gambar')->nullable(); // path foto QRIS (jenis qris)
            $table->boolean('aktif')->default(true);
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['aktif', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_channels');
    }
};
