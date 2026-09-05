<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_catalog_id')->constrained();
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('jumlah_unit')->default(1);
            $table->text('alamat_pengerjaan')->nullable();
            $table->date('tanggal_jadwal')->nullable();
            $table->time('jam_jadwal')->nullable();
            $table->string('status')->default('baru');
            $table->text('catatan_admin')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'tanggal_jadwal']);
            $table->index('teknisi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
