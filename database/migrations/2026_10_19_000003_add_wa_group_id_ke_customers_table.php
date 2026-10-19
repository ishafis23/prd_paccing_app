<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu "Cek ID Group WA" — tautkan customer ke JID grup WA bersama (mis.
 * grup koordinasi kantor/korporat), supaya dokumentasi pengerjaan (resi)
 * bisa juga dikirim ke grup itu, bukan cuma nomor pribadi customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'wa_group_id')) {
                $table->string('wa_group_id')->nullable()->after('catatan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'wa_group_id')) {
                $table->dropColumn('wa_group_id');
            }
        });
    }
};
