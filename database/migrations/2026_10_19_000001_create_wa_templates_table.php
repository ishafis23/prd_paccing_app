<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/27 fase 27-A: template pesan WA yang bisa DIKELOLA ADMIN (isi
 * pesan + placeholder), agar perubahan kata-kata tidak perlu deploy ulang.
 * Baris default diisi oleh WaTemplateSeeder dari WaTemplateService::DEFAULTS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_templates')) {
            Schema::create('wa_templates', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 60)->unique();
                $table->string('nama', 150);
                $table->text('isi');
                $table->boolean('aktif')->default(true);
                $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};
