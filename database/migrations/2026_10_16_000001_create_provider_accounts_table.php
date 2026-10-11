<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/23 fase 23-A: akun gateway WhatsApp (mis. wablasted.com).
 * Satu akun punya `iduser` (identitas di provider) + base URL per jenis
 * endpoint (bila kosong, pakai config('wa.base_url.*')).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_accounts')) {
            Schema::create('provider_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('label');
                $table->string('iduser')->unique();
                $table->string('base_url_qr')->nullable();
                $table->string('base_url_send')->nullable();
                $table->string('base_url_group')->nullable();
                $table->string('base_url_document')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
    }
};
