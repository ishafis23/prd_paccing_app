<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 14 Nov 2026 poin 17: bukti (screenshot/dokumentasi) bahwa kendala sudah
        // disampaikan ke customer. Append-only: alasan_kendala di orders dihapus saat jadwal ulang,
        // log ini tetap menyimpan riwayatnya.
        Schema::create('order_kendala_buktis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jenis', 10);              // besok | sore
            $table->string('pihak', 10)->nullable();  // customer | teknisi (sumber kendala)
            $table->text('alasan');
            $table->string('foto_path')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_kendala_buktis');
    }
};
