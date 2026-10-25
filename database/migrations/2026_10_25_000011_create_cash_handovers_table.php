<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal_setor');
            $table->decimal('nominal', 14, 2);
            $table->string('diserahkan_ke', 150); // nama orang / tujuan setor
            $table->string('foto_path'); // bukti foto setoran (disk public)
            $table->text('catatan')->nullable();
            $table->string('status')->default('menunggu'); // menunggu|diterima|ditolak
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diterima_pada')->nullable();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['teknisi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handovers');
    }
};
