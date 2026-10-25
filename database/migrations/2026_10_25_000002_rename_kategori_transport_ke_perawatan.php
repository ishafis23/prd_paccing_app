<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kategori pengeluaran teknisi `transport` diganti `perawatan` (permintaan client).
 * Kolom `kategori` di `teknisi_expenses` bertipe string (bukan enum), jadi tidak
 * perlu ALTER — cukup pindahkan baris lama. Tabel legacy `teknis_expenses` tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('teknisi_expenses')->where('kategori', 'transport')->update(['kategori' => 'perawatan']);
    }

    public function down(): void
    {
        // Sengaja kosong: baris `perawatan` lama tidak bisa dibedakan dari baris
        // `perawatan` baru, jadi nama kategori tidak dikembalikan otomatis.
    }
};
