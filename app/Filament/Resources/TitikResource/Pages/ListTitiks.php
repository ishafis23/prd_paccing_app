<?php

namespace App\Filament\Resources\TitikResource\Pages;

use App\Filament\Resources\TitikResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTitiks extends ListRecords
{
    protected static string $resource = TitikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
