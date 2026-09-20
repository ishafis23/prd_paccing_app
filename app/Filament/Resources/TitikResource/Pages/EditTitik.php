<?php

namespace App\Filament\Resources\TitikResource\Pages;

use App\Filament\Resources\TitikResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTitik extends EditRecord
{
    protected static string $resource = TitikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
