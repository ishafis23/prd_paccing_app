<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teknisi_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'teknisi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_technicians');
    }
};
