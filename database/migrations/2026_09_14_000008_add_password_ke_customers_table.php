<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal Customer (dev-plan/12 §3.6, dev-plan/portal-customer/01-...md §4)
 * — keputusan 13 Sept: 1 akun login per customer (bukan multi-user staf),
 * pakai data customer yg sudah ada (email) + password baru ini. Nullable
 * — customer tanpa password TIDAK bisa login (portal belum diaktifkan
 * utk customer itu), admin yg mengaktifkan lewat aksi "Atur Password
 * Portal" di CustomerResource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
