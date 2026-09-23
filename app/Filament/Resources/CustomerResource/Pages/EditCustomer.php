<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Services\CustomerAddressSyncService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * dev-plan/admin/03 (B75) — sama seperti CreateCustomer::afterCreate(),
     * utk customer lama yang baru sekarang diisi "Alamat Utama"-nya lewat
     * Edit.
     */
    protected function afterSave(): void
    {
        app(CustomerAddressSyncService::class)->syncIfMissing($this->record);
    }
}
