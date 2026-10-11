<?php

namespace App\Filament\Resources\ServiceTypeOptionResource\Pages;

use App\Filament\Resources\ServiceTypeOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceTypeOptions extends ListRecords
{
    protected static string $resource = ServiceTypeOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Jenis Layanan'),
        ];
    }
}
