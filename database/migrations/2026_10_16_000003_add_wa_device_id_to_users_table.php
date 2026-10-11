<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/23 fase 23-A: device WA milik user (setara users.device_id di
 * arm_app). Dijaga konsisten dengan wa_devices.assigned_user_id lewat event
 * model WaDevice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wa_device_id')) {
                $table->foreignId('wa_device_id')->nullable()->after('status')
                    ->constrained('wa_devices')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wa_device_id')) {
                $table->dropConstrainedForeignId('wa_device_id');
            }
        });
    }
};
