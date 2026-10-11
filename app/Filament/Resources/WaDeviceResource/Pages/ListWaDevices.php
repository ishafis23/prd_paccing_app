<?php

namespace App\Filament\Resources\WaDeviceResource\Pages;

use App\Filament\Resources\WaDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaDevices extends ListRecords
{
    protected static string $resource = WaDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
