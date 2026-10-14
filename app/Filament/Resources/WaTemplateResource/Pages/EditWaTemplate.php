<?php

namespace App\Filament\Resources\WaTemplateResource\Pages;

use App\Filament\Resources\WaTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaTemplate extends EditRecord
{
    protected static string $resource = WaTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
