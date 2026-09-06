<?php

namespace App\Filament\Resources\ServiceCatalogResource\Pages;

use App\Filament\Resources\ServiceCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceCatalog extends EditRecord
{
    protected static string $resource = ServiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
