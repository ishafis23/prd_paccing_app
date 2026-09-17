<?php

namespace App\Filament\Resources\MotorCleaningResource\Pages;

use App\Filament\Resources\MotorCleaningResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotorCleanings extends ListRecords
{
    protected static string $resource = MotorCleaningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Cuci Motor')
                ->visible(fn (): bool => MotorCleaningResource::bolehKelola()),
        ];
    }
}
