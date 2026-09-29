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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teknisi_id')->constrained('users');
            $table->date('tanggal_laporan');
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamps();
            $table->unique(['teknisi_id', 'tanggal_laporan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
