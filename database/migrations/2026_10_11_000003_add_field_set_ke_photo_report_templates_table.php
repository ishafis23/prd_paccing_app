<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: `field_set` menentukan keterangan apa yang diminta untuk sebuah
 * slot foto — indoor_lengkap (posisi + suhu wajib + RPM + kondisi),
 * outdoor (posisi + RPM + kondisi, TANPA suhu indoor), bebas (tanpa
 * keterangan). Default `bebas` supaya template lama / buatan admin tidak
 * tiba-tiba menuntut keterangan. Seed awal hanya untuk slot Cuci AC yang
 * memang bernama "...(Indoor)" / "...(Outdoor)".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('photo_report_templates', 'field_set')) {
            Schema::table('photo_report_templates', function (Blueprint $table) {
                $table->string('field_set')->default('bebas')->after('label');
            });
        }

        DB::table('photo_report_templates')
            ->where('kategori', 'cuci_ac')
            ->where('kode_slot', 'like', '%_indoor')
            ->where('field_set', 'bebas')
            ->update(['field_set' => 'indoor_lengkap']);

        DB::table('photo_report_templates')
            ->where('kategori', 'cuci_ac')
            ->where('kode_slot', 'like', '%_outdoor')
            ->where('field_set', 'bebas')
            ->update(['field_set' => 'outdoor']);
    }

    public function down(): void
    {
        Schema::table('photo_report_templates', function (Blueprint $table) {
            $table->dropColumn('field_set');
        });
    }
};
