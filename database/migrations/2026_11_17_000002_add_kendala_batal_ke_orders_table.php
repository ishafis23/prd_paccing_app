<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 17 Nov 2026 poin 32: ukur tingkat pekerjaan terkendala & batal
        // oleh customer terhadap pekerjaan yang sudah dijadwalkan.
        // - kendala_pertama_pada/kendala_pihak: tidak dihapus saat jadwal ulang
        //   (alasan_kendala dibersihkan), supaya statistik tidak hilang.
        // - dibatalkan_oleh: customer | internal (null = tidak dicatat / data lama).
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('kendala_pertama_pada')->nullable()->after('jenis_kendala');
            $table->string('kendala_pihak', 10)->nullable()->after('kendala_pertama_pada');
            $table->timestamp('dibatalkan_pada')->nullable()->after('kendala_pihak');
            $table->string('dibatalkan_oleh', 10)->nullable()->after('dibatalkan_pada');
        });

        // Isi data lama sebisanya.
        DB::table('orders')
            ->where(function ($q): void {
                $q->where('status', 'terkendala')
                    ->orWhereNotNull('alasan_kendala')
                    ->orWhere('catatan_admin', 'like', '%[KENDALA%')
                    ->orWhereIn('id', DB::table('order_kendala_buktis')->select('order_id'));
            })
            ->update(['kendala_pertama_pada' => DB::raw('updated_at')]);

        DB::table('orders')->where('status', 'batal')->update(['dibatalkan_pada' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['kendala_pertama_pada', 'kendala_pihak', 'dibatalkan_pada', 'dibatalkan_oleh']);
        });
    }
};
