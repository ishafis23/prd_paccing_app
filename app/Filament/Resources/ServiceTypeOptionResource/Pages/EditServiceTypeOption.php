<?php

namespace App\Filament\Resources\ServiceTypeOptionResource\Pages;

use App\Filament\Resources\ServiceTypeOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceTypeOption extends EditRecord
{
    protected static string $resource = ServiceTypeOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
