<?php

namespace App\Enums;

enum IncomeCategory: string
{
    case Jasa = 'jasa';
    case Material = 'material';

    /**
     * Kata kunci nama baris yang terbaca sebagai barang/sparepart.
     */
    private const KATA_BARANG = [
        'sparepart', 'spare part', 'kapasitor', 'capacitor', 'freon', 'pipa', 'kabel', 'bracket', 'breket',
        'kompresor', 'compressor', 'motor', 'fan', 'remote', 'filter', 'thermostat', 'sensor', 'pcb',
        'modul', 'ganti ', 'material', 'barang', 'selang',
    ];

    /**
     * Cuci/Service AC = jasa; layanan lain (pengadaan, dll.) = material.
     * Aturan lama — dipakai backfill & mode katalog "otomatis".
     */
    public static function untukLayanan(?ServiceType $jenis): self
    {
        return in_array($jenis, [ServiceType::CuciAc, ServiceType::ServiceAc], true) ? self::Jasa : self::Material;
    }

    /**
     * Default pilihan Jasa/Material di form "Tambah Layanan" (teknisi/admin):
     * material bila kategori Pengadaan atau nama terbaca sebagai barang,
     * selain itu jasa (Cuci/Service/Instalasi/dll.). User tetap bisa mengganti.
     */
    public static function defaultUntukBaris(?ServiceType $kategori, ?string $nama = null): self
    {
        if ($kategori === ServiceType::PengadaanAc) {
            return self::Material;
        }

        $nama = mb_strtolower((string) $nama);
        foreach (self::KATA_BARANG as $kata) {
            if ($nama !== '' && str_contains($nama, $kata)) {
                return self::Material;
            }
        }

        return self::Jasa;
    }

    public function label(): string
    {
        return $this === self::Jasa ? 'Jasa' : 'Material';
    }
}
