<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['teknisi_ids'] = $this->record->members->pluck('id')->all();

        return $data;
    }

    /**
     * Sama pola dgn CreateTeam::handleRecordCreation() — `teknisi_ids`
     * disinkron manual ke `team_members`, bukan kolom asli.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $teknisiIds = array_values($data['teknisi_ids'] ?? []);
        unset($data['teknisi_ids']);

        $data['pic_teknisi_id'] = $teknisiIds[0] ?? null;

        $record->update($data);
        $record->members()->sync($teknisiIds);

        return $record;
    }
}
