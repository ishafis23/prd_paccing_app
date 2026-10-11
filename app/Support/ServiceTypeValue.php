<?php

namespace App\Support;

/**
 * Nilai jenis layanan TAMBAHAN (dev-plan/26) — dipakai saat kolom berisi kode
 * yang bukan bagian enum `ServiceType` bawaan. Punya `->value` & `->label()`
 * supaya konsumen lama (yang membaca `->value`) tetap jalan.
 */
final class ServiceTypeValue
{
    public function __construct(public string $value, public string $label) {}

    public function label(): string
    {
        return $this->label;
    }
}
