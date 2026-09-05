<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('metode')->default('cash');
            $table->string('status')->default('belum_bayar');
            $table->decimal('total_tagihan', 12, 2);
            $table->decimal('jumlah_dibayar', 12, 2)->default(0);
            $table->date('tanggal_bayar')->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'tanggal_bayar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
