<?php

namespace App\Filament\Resources\TechnicianAdvanceResource\Pages;

use App\Filament\Resources\TechnicianAdvanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicianAdvance extends EditRecord
{
    protected static string $resource = TechnicianAdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
