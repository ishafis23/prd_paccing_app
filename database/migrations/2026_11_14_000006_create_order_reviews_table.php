<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 14 Nov 2026 poin 8: ulasan customer atas pekerjaan, diisi lewat tautan resi publik.
        Schema::create('order_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); // satu ulasan per order
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete(); // PIC saat ulasan dibuat
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('komentar')->nullable();
            $table->timestamps();
            $table->index(['teknisi_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
