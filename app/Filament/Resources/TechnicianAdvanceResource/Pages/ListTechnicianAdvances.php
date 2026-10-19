<?php

namespace App\Filament\Resources\TechnicianAdvanceResource\Pages;

use App\Filament\Resources\TechnicianAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianAdvances extends ListRecords
{
    protected static string $resource = TechnicianAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Catat Kasbon'),
        ];
    }
}
