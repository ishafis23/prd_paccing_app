<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense tracking untuk teknisi (dev-plan/teknisi/fase03):
 * Teknisi input pengeluaran harian (bensin, makan, material, dll).
 * Admin dapat approve/reject dengan kemungkinan edit nominal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknis_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teknisi_id')->constrained('users')->cascadeOnDelete();

            // Expense info
            $table->enum('kategori', ['bensin', 'makan', 'material', 'transport', 'lainnya'])->comment('Kategori pengeluaran');
            $table->unsignedInteger('nominal')->comment('Nominal dalam IDR');
            $table->text('keterangan')->nullable()->comment('Deskripsi pengeluaran');

            // Bukti/Receipt (optional)
            $table->string('bukti_file')->nullable()->comment('Path ke foto/scan bukti');

            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Status approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->comment('Admin yang approve');
            $table->text('catatan_approval')->nullable()->comment('Catatan saat approval/rejection');

            // Date info
            $table->date('tanggal_input')->comment('Tanggal pengeluaran terjadi');
            $table->dateTime('tanggal_approve')->nullable()->comment('Tanggal di-approve');

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('teknisi_id');
            $table->index('status');
            $table->index('tanggal_input');
            $table->index('kategori');
            $table->index(['teknisi_id', 'status']);
            $table->index(['teknisi_id', 'tanggal_input']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknis_expenses');
    }
};
