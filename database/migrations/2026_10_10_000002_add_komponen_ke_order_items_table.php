<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (dev-plan/21 §3): omset dipisah jasa vs material per baris item.
 * `komponen` selalu terisi. Backfill memakai aturan lama (IncomeCategory::
 * untukLayanan): kategori Cuci/Service = jasa, selainnya = material. Tidak
 * mengubah harga/jumlah sehingga total historis tetap.
 * `penyesuaian` menandai baris koreksi total (OrderService::koreksiTotal)
 * agar tidak dihitung sebagai "unit".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'komponen')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('komponen')->default('material')->after('kategori');
                $table->boolean('penyesuaian')->default(false)->after('komponen');
            });
        }

        DB::table('order_items')
            ->whereIn('kategori', ['cuci_ac', 'service_ac'])
            ->update(['komponen' => 'jasa']);

        DB::table('order_items')
            ->where(fn ($q) => $q->whereNull('kategori')->orWhereNotIn('kategori', ['cuci_ac', 'service_ac']))
            ->update(['komponen' => 'material']);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['komponen', 'penyesuaian']);
        });
    }
};
