<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EditHeroSlide extends EditRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $gambarLama = $record->gambar;
        $updated = parent::handleRecordUpdate($record, $data);

        // B38c: file gambar lama dibuang saat diganti (anti file yatim).
        if ($gambarLama && filled($updated->gambar) && $updated->gambar !== $gambarLama) {
            Storage::disk('public')->delete($gambarLama);
        }

        return $updated;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
