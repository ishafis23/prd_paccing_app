<?php

namespace App\Support;

use App\Enums\ServiceType;

/**
 * Template slot foto laporan per kategori order_item (dev-plan/13 §3).
 * Nama & jumlah slot boleh disesuaikan bebas tanpa diskusi ulang (sudah
 * disetujui 13 Sept). PengadaanAc & kategori kosong dipakaikan template
 * umum sebelum/sesudah (tidak eksplisit disebut di daftar chat client,
 * tapi tetap butuh dokumentasi foto).
 *
 * @return array<string, string> slot key => label, terurut sesuai urutan tampil
 */
class FotoLaporanSlot
{
    public static function untuk(?ServiceType $kategori): array
    {
        return match ($kategori) {
            ServiceType::CuciAc => [
                'outdoor_proses' => 'Outdoor - Proses',
                'indoor_proses' => 'Indoor - Proses',
                'indoor_sebelum' => 'Indoor - Sebelum',
                'indoor_sesudah_suhu' => 'Indoor - Sesudah (Cek Suhu)',
            ],
            ServiceType::TambahFreon => [
                'tekanan_sebelum' => 'Tekanan Sebelum',
                'tekanan_sesudah' => 'Tekanan Sesudah',
            ],
            ServiceType::ServiceAc => [
                'kondisi_sebelum' => 'Kondisi Sebelum',
                'proses_service' => 'Proses Service',
                'kondisi_sesudah' => 'Kondisi Sesudah',
            ],
            ServiceType::Instalasi => [
                'lokasi_sebelum' => 'Lokasi Sebelum',
                'unit_terpasang' => 'Unit Terpasang',
                'testing_suhu' => 'Testing Suhu',
            ],
            ServiceType::Relokasi => [
                'lokasi_asal' => 'Lokasi Asal',
                'lokasi_baru' => 'Lokasi Baru',
                'unit_terpasang' => 'Unit Terpasang',
            ],
            ServiceType::Bongkar => [
                'sebelum_bongkar' => 'Sebelum Bongkar',
                'sesudah_bongkar' => 'Sesudah Bongkar',
            ],
            default => [
                'sebelum' => 'Sebelum',
                'sesudah' => 'Sesudah',
            ],
        };
    }
}
