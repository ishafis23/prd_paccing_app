<?php

namespace App\Filament\Resources\WaDeviceResource\Pages;

use App\Filament\Resources\WaDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaDevice extends EditRecord
{
    protected static string $resource = WaDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
