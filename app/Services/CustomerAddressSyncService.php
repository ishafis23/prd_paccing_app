<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;

/**
 * Sinkron `customers.alamat` (field teks tunggal "Alamat Utama") ke
 * `customer_addresses` (dev-plan/14, sumber kebenaran alamat utk wizard
 * Buat Order) — dev-plan/admin/03. SATU ARAH & SEKALI SAJA per customer:
 * begitu customer sudah punya minimal satu `CustomerAddress`, tidak
 * ditimpa lagi (perubahan alamat selanjutnya lewat tab "Alamat").
 */
class CustomerAddressSyncService
{
    /**
     * Dipanggil dari `Customer::booted()` (jalur yg memicu Eloquent event
     * — create/update biasa via Filament) setiap kali customer disimpan.
     */
    public function syncIfMissing(Customer $customer): ?CustomerAddress
    {
        if (blank($customer->alamat)) {
            return null;
        }

        if ($customer->addresses()->exists()) {
            return null;
        }

        return CustomerAddress::create([
            'customer_id' => $customer->id,
            'nama_lokasi' => 'Alamat Utama',
            'alamat' => $customer->alamat,
            'is_utama' => true,
        ]);
    }

    /**
     * Backfill utk customer yang tidak lewat Eloquent event — Import Excel
     * (`CustomerImportService::import()` pakai bulk `insert()`) & data lama
     * sebelum fitur ini ada. Idempoten, aman dipanggil berulang.
     */
    public function backfillMissing(): int
    {
        $dibuat = 0;

        Customer::query()
            ->whereNotNull('alamat')
            ->where('alamat', '!=', '')
            ->whereDoesntHave('addresses')
            ->chunkById(200, function ($customers) use (&$dibuat): void {
                foreach ($customers as $customer) {
                    if ($this->syncIfMissing($customer) !== null) {
                        $dibuat++;
                    }
                }
            });

        return $dibuat;
    }
}
