<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('interval_bulan')->nullable();
            $table->date('tanggal_servis_berikutnya');
            $table->string('status_notice')->default('belum_jatuh_tempo');
            $table->timestamps();
            $table->index(['status_notice', 'tanggal_servis_berikutnya']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
    }
};
