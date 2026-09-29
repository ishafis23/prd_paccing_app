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
        Schema::create('daily_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->integer('saldo_awal_dari_base')->default(0); // Ambil dari warung/base
            $table->integer('pendapatan_dari_customer')->default(0); // Uang dari customer
            $table->integer('total_pengeluaran')->storedAs('0'); // Calculated from expense_items
            $table->integer('jumlah_setoran')->nullable(); // Sisa cash untuk disetor
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_financial_entries');
    }
};
