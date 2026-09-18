<?php

use App\Enums\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/17: template slot foto laporan per kategori, dipindah dari
 * hardcode (`App\Support\FotoLaporanSlot`) ke tabel yang bisa dikelola
 * admin (B61) — aktif/nonaktif per item + tambah item baru tanpa deploy.
 * `kode_slot` di-generate dari label & terkunci (B62). Cuci AC (daftar
 * baru dari client) diisi `wajib=true` semua; kategori lain pakai daftar
 * lama sbg titik awal, `wajib=false` dulu (B63/B64).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kategori');
            $table->string('kode_slot');
            $table->string('label');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('wajib')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['kategori', 'kode_slot']);
        });

        $sekarang = now();

        $buatBaris = function (string $kategori, array $items, bool $wajib) use ($sekarang): array {
            $urutan = 0;
            $baris = [];

            foreach ($items as $kodeSlot => $label) {
                $baris[] = [
                    'kategori' => $kategori,
                    'kode_slot' => $kodeSlot,
                    'label' => $label,
                    'urutan' => $urutan++,
                    'wajib' => $wajib,
                    'aktif' => true,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];
            }

            return $baris;
        };

        $data = [
            ...$buatBaris(ServiceType::CuciAc->value, [
                'foto_tampak_depan_lokasi' => 'Foto Tampak Depan Lokasi',
                'foto_sesudah_cuci_indoor' => 'Foto Sesudah Cuci (Indoor)',
                'foto_sesudah_cuci_outdoor' => 'Foto Sesudah Cuci (Outdoor)',
                'foto_area_unit_indoor' => 'Foto Area Unit (Indoor)',
                'foto_area_unit_outdoor' => 'Foto Area Unit (Outdoor)',
                'foto_cek_suhu_indoor' => 'Foto Cek Suhu (Indoor)',
            ], wajib: true),
            ...$buatBaris(ServiceType::ServiceAc->value, [
                'kondisi_sebelum' => 'Kondisi Sebelum',
                'proses_service' => 'Proses Service',
                'kondisi_sesudah' => 'Kondisi Sesudah',
            ], wajib: false),
            ...$buatBaris(ServiceType::TambahFreon->value, [
                'tekanan_sebelum' => 'Tekanan Sebelum',
                'tekanan_sesudah' => 'Tekanan Sesudah',
            ], wajib: false),
            ...$buatBaris(ServiceType::Instalasi->value, [
                'lokasi_sebelum' => 'Lokasi Sebelum',
                'unit_terpasang' => 'Unit Terpasang',
                'testing_suhu' => 'Testing Suhu',
            ], wajib: false),
            ...$buatBaris(ServiceType::Relokasi->value, [
                'lokasi_asal' => 'Lokasi Asal',
                'lokasi_baru' => 'Lokasi Baru',
                'unit_terpasang' => 'Unit Terpasang',
            ], wajib: false),
            ...$buatBaris(ServiceType::Bongkar->value, [
                'sebelum_bongkar' => 'Sebelum Bongkar',
                'sesudah_bongkar' => 'Sesudah Bongkar',
            ], wajib: false),
        ];

        DB::table('photo_report_templates')->insert($data);
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_report_templates');
    }
};
