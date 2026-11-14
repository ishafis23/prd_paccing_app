<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 14 Nov 2026 poin 19 & 20:
 *  - orders.keluhan: keluhan customer saat order (tampil di riwayat pelayanan CRM);
 *  - customers.tipe_customer: penanda customer baru/lama. Selama migrasi sistem semua
 *    customer otomatis "baru"; admin menandai customer lama secara manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('keluhan')->nullable()->after('catatan_admin');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('tipe_customer', 10)->default('baru')->after('status')->index();
            $table->timestamp('tipe_diubah_at')->nullable()->after('tipe_customer');
            $table->foreignId('tipe_diubah_oleh')->nullable()->after('tipe_diubah_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipe_diubah_oleh');
            $table->dropColumn(['tipe_customer', 'tipe_diubah_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('keluhan');
        });
    }
};
