<?php

namespace App\Filament\Resources\ProviderAccountResource\Pages;

use App\Filament\Resources\ProviderAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderAccounts extends ListRecords
{
    protected static string $resource = ProviderAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
