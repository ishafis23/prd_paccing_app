<?php

namespace App\Filament\Resources\WaTemplateResource\Pages;

use App\Filament\Resources\WaTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaTemplates extends ListRecords
{
    protected static string $resource = WaTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Template'),
        ];
    }
}
