<?php

namespace App\Filament\Resources\TechnicianPayrollProfileResource\Pages;

use App\Filament\Resources\TechnicianPayrollProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianPayrollProfiles extends ListRecords
{
    protected static string $resource = TechnicianPayrollProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Profil Gaji'),
        ];
    }
}
