<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration untuk Game 2 deadline time (dev-plan/teknisi/fase03):
 * Menyimpan deadline waktu untuk upload foto Game 2 (default: 08:30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game2_settings', function (Blueprint $table) {
            $table->id();
            $table->time('deadline_time')->default('08:30:00')->comment('Deadline jam untuk Game 2 upload photo');
            $table->boolean('active')->default(true)->comment('Apakah Game 2 lock aktif');
            $table->timestamps();
        });

        // Insert default config
        DB::table('game2_settings')->insert([
            'deadline_time' => '08:30:00',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('game2_settings');
    }
};
