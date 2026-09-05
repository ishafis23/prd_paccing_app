<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_report_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained();
            $table->unsignedInteger('jumlah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_report_materials');
    }
};
