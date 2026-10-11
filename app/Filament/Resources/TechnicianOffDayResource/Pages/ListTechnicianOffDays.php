<?php

namespace App\Filament\Resources\TechnicianOffDayResource\Pages;

use App\Filament\Resources\TechnicianOffDayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianOffDays extends ListRecords
{
    protected static string $resource = TechnicianOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Hari OFF'),
        ];
    }
}
