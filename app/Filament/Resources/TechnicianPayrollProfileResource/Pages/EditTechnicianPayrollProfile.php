<?php

namespace App\Filament\Resources\TechnicianPayrollProfileResource\Pages;

use App\Filament\Resources\TechnicianPayrollProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianPayrollProfile extends EditRecord
{
    protected static string $resource = TechnicianPayrollProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
