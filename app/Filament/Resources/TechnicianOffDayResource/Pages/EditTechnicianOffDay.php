<?php

namespace App\Filament\Resources\TechnicianOffDayResource\Pages;

use App\Filament\Resources\TechnicianOffDayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianOffDay extends EditRecord
{
    protected static string $resource = TechnicianOffDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
