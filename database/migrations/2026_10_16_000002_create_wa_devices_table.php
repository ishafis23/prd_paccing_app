<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/23 fase 23-A: device WhatsApp = satu nomor WA tersambung.
 * `connection_status` disinkronkan dari pre-flight get-qr (bukan dipercaya
 * dari kolom saja) — konsep arm_app pages/wablasted/proxy.php:244-278.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_devices')) {
            Schema::create('wa_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_account_id')->constrained('provider_accounts')->cascadeOnDelete();
                $table->string('device_name')->unique();
                $table->string('nomor_wa')->nullable();
                $table->string('status', 20)->default('tersedia'); // tersedia | terpakai | terputus
                $table->string('connection_status', 20)->default('belum_terhubung'); // terhubung | belum_terhubung
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();

                $table->index('connection_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_devices');
    }
};
