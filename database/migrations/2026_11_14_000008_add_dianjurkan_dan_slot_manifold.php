<?php

use App\Enums\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 14 Nov 2026 poin 22 — layanan Isi Freon (kategori tambah_freon) perlu tempat foto
 * ukuran manifold sebelum & sesudah. Tidak wajib, tetapi ditekankan: kolom `dianjurkan`
 * menampilkan penanda "Dianjurkan" di form teknisi tanpa menahan laporan/keberangkatan.
 */
return new class extends Migration
{
    private const KATEGORI = 'tambah_freon';

    public function up(): void
    {
        Schema::table('photo_report_templates', function (Blueprint $table) {
            $table->boolean('dianjurkan')->default(false)->after('wajib');
        });

        $urutan = (int) DB::table('photo_report_templates')->where('kategori', self::KATEGORI)->max('urutan');
        $sekarang = now();

        // Kode `tekanan_*` sudah ada sejak template awal (bacaan tekanan manifold) — dipakai ulang agar tidak dobel.
        foreach (['tekanan_sebelum' => 'Ukuran Manifold Sebelum', 'tekanan_sesudah' => 'Ukuran Manifold Sesudah'] as $kode => $label) {
            $ada = DB::table('photo_report_templates')->where('kategori', self::KATEGORI)->where('kode_slot', $kode)->first();

            if ($ada !== null) {
                DB::table('photo_report_templates')->where('id', $ada->id)->update(['label' => $label, 'dianjurkan' => true, 'aktif' => true, 'updated_at' => $sekarang]);

                continue;
            }

            DB::table('photo_report_templates')->insert([
                'kategori' => self::KATEGORI,
                'kode_slot' => $kode,
                'label' => $label,
                'urutan' => ++$urutan,
                'wajib' => false,
                'dianjurkan' => true,
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }

        foreach (['', '.wajib', '.dianjurkan', '.fieldset', '.fieldset.wajib'] as $akhiran) {
            Cache::forget('photo_report_templates.'.self::KATEGORI.$akhiran);
        }
    }

    public function down(): void
    {
        DB::table('photo_report_templates')->where('kategori', self::KATEGORI)
            ->whereIn('kode_slot', ['tekanan_sebelum', 'tekanan_sesudah'])->update(['label' => DB::raw("CASE kode_slot WHEN 'tekanan_sebelum' THEN 'Tekanan Sebelum' ELSE 'Tekanan Sesudah' END")]);

        Schema::table('photo_report_templates', function (Blueprint $table) {
            $table->dropColumn('dianjurkan');
        });
    }
};
