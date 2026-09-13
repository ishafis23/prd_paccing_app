<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tim Teknisi permanen (dev-plan/12 §3.13) — client minta menu SPK dgn
 * tim baku (mis. 1 tim = 2 teknisi) yg dipilih sekali lalu dipakai
 * berulang, bukan pilih teknisi satu-satu tiap order (order_technicians
 * ad-hoc yg sudah ada tetap dipakai sbg pencatatan aktual per order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->foreignId('pic_teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
