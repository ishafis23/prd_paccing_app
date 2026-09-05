<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('no_hp');
            $table->text('alamat')->nullable();
            $table->string('area')->default('makassar');
            $table->string('sumber_lead')->default('whatsapp');
            $table->string('status')->default('lead');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['area', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
