<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/admin/03 (B77/B78): admin bisa sesuaikan `total_tagihan` saat
 * Catat Pembayaran (ongkir/material tambahan, atau diskon) — wajib isi
 * alasan penyesuaian saat itu terjadi, tersimpan di sini utk audit
 * Finance/Owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('jumlah_dibayar');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('catatan');
        });
    }
};
