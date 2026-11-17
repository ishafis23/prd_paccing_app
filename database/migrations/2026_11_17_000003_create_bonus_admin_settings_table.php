<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 17 Nov 2026 poin 24: bonus Admin/HRD/Finance = persen dari omset
        // jasa, hanya bila omset jasa periode mencapai minimal tertentu.
        // Baris tunggal (pola sama dgn business_infos / attendance_settings).
        Schema::create('bonus_admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('aktif')->default(true);
            $table->decimal('persen', 5, 2)->default(1);
            $table->decimal('minimal_omset_jasa', 14, 2)->default(50000000);
            $table->string('mode', 12)->default('per_orang'); // per_orang | dibagi_rata
            $table->json('peran')->nullable(); // ['admin','hr','finance']
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_admin_settings');
    }
};
