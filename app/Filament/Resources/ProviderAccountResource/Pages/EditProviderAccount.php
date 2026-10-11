<?php

namespace App\Filament\Resources\ProviderAccountResource\Pages;

use App\Filament\Resources\ProviderAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderAccount extends EditRecord
{
    protected static string $resource = ProviderAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
