<?php

namespace App\Filament\Resources\TechnicianAdvanceResource\Pages;

use App\Filament\Resources\TechnicianAdvanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicianAdvance extends CreateRecord
{
    protected static string $resource = TechnicianAdvanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['dicatat_oleh'] = auth()->id();

        return $data;
    }
}
