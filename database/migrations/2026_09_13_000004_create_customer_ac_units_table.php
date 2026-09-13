<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ac_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kode_unit');
            $table->string('kode_ruangan');
            $table->string('jenis_unit')->nullable();
            $table->string('pk')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'kode_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ac_units');
    }
};
