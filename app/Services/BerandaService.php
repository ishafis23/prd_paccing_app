<?php

namespace App\Services;

use App\Models\BerandaSetting;
use App\Models\HeroSlide;
use App\Models\ServiceCatalog;

/**
 * Konten landing page (B38): slide hero, layanan beranda, pengaturan seksi.
 */
class BerandaService
{
    /**
     * Slide hero aktif, urut sesuai `urutan` (fallback: kosong → landing
     * memakai hero gradasi bawaan).
     */
    public function slidesAktif(): \Illuminate\Database\Eloquent\Collection
    {
        return HeroSlide::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }

    /**
     * Layanan yang tampil di beranda (B38d): yang `tampil_beranda=true`
     * diurutkan; bila belum ada pilihan → semua layanan aktif (fallback).
     */
    public function layananBeranda(): \Illuminate\Database\Eloquent\Collection
    {
        $terpilih = ServiceCatalog::query()
            ->where('aktif', true)
            ->where('tampil_beranda', true)
            ->orderBy('urutan_beranda')
            ->orderBy('id')
            ->get();

        if ($terpilih->isNotEmpty()) {
            return $terpilih;
        }

        return ServiceCatalog::query()
            ->where('aktif', true)
            ->orderBy('jenis_layanan')
            ->orderBy('harga')
            ->get();
    }

    /**
     * Pengaturan seksi landing (baris tunggal, otomatis dibuat default).
     */
    public function settings(): BerandaSetting
    {
        $setting = BerandaSetting::query()->first();

        if ($setting) {
            return $setting;
        }

        return BerandaSetting::create([
            'maps_embed' => null,
            'jam_operasional' => null,
            'sosmed_instagram' => null,
            'sosmed_facebook' => null,
            'tampil_layanan' => true,
            'tampil_cara_kerja' => true,
            'tampil_area' => true,
            'tampil_peta' => true,
        ]);
    }

    /**
     * @param  array{maps_embed?: ?string, jam_operasional?: ?string, sosmed_instagram?: ?string, sosmed_facebook?: ?string, tampil_layanan?: bool, tampil_cara_kerja?: bool, tampil_area?: bool, tampil_peta?: bool}  $data
     */
    public function simpanSettings(array $data): BerandaSetting
    {
        $setting = $this->settings();

        $setting->maps_embed = $this->nullJikaKosong($data['maps_embed'] ?? null);
        $setting->jam_operasional = $this->nullJikaKosong($data['jam_operasional'] ?? null);
        $setting->sosmed_instagram = $this->nullJikaKosong($data['sosmed_instagram'] ?? null);
        $setting->sosmed_facebook = $this->nullJikaKosong($data['sosmed_facebook'] ?? null);
        $setting->tampil_layanan = (bool) ($data['tampil_layanan'] ?? true);
        $setting->tampil_cara_kerja = (bool) ($data['tampil_cara_kerja'] ?? true);
        $setting->tampil_area = (bool) ($data['tampil_area'] ?? true);
        $setting->tampil_peta = (bool) ($data['tampil_peta'] ?? true);
        $setting->save();

        return $setting->fresh();
    }

    private function nullJikaKosong(mixed $nilai): ?string
    {
        $nilai = is_string($nilai) ? trim($nilai) : $nilai;

        return ($nilai === null || $nilai === '') ? null : (string) $nilai;
    }
}
