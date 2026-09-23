<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\CustomerAddressSyncService;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * dev-plan/admin/03 (B75) — begitu "Alamat Utama" terisi & customer
     * baru belum punya `customer_addresses` sama sekali, otomatis buat 1
     * baris supaya wizard Buat Order (dropdown "Pilih Alamat") tidak
     * pernah tampak kosong utk customer yang sebenarnya sudah punya
     * alamat. Dipasang di level PAGE (bukan Customer::booted()) supaya
     * TIDAK ikut jalan di factory/seeder/test lain yang bikin Customer
     * apa adanya.
     */
    protected function afterCreate(): void
    {
        app(CustomerAddressSyncService::class)->syncIfMissing($this->record);
    }
}
