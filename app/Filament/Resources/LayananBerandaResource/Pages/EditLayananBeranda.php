<?php

namespace App\Filament\Resources\LayananBerandaResource\Pages;

use App\Filament\Resources\LayananBerandaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EditLayananBeranda extends EditRecord
{
    protected static string $resource = LayananBerandaResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $gambarLama = $record->gambar;
        $updated = parent::handleRecordUpdate($record, $data);

        // B38d: gambar lama dibuang saat diganti/dihapus.
        if ($gambarLama && $updated->gambar !== $gambarLama) {
            Storage::disk('public')->delete($gambarLama);
        }

        return $updated;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
