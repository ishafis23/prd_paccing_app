<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Revisi 17 Nov 2026 poin 31: "Pengecekan" jadi jenis layanan sendiri
        // (jasa) agar pendapatannya terpisah di rincian per teknisi.
        // Admin bisa mengubah label/urutan di menu Jenis Layanan.
        if (DB::table('service_types')->where('kode', 'pengecekan')->doesntExist()) {
            DB::table('service_types')->insert([
                'kode' => 'pengecekan',
                'label' => 'Pengecekan',
                'komponen' => 'jasa',
                'bukan_omset' => false,
                'urutan' => 10,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('service_types')->where('kode', 'pengecekan')->delete();
    }
};
