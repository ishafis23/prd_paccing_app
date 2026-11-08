<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail umum (append-only) untuk perubahan yang berdampak ke uang:
 * pembayaran, koreksi total, edit omset, sinkron total. Menyimpan nilai sebelum/sesudah + pelaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 60); // mis. payment.sinkron_total
            $table->string('subjek_type')->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('sebelum')->nullable();
            $table->json('sesudah')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index(['subjek_type', 'subjek_id']);
            $table->index('aksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
