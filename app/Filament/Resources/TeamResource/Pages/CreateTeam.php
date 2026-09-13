<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    /**
     * `teknisi_ids` bukan kolom asli — teknisi PERTAMA jadi PIC
     * (`pic_teknisi_id`), semuanya (termasuk PIC) disinkron ke
     * `team_members` lewat relasi `members()`.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $teknisiIds = array_values($data['teknisi_ids'] ?? []);
        unset($data['teknisi_ids']);

        $data['pic_teknisi_id'] = $teknisiIds[0] ?? null;

        $team = static::getModel()::create($data);
        $team->members()->sync($teknisiIds);

        return $team;
    }
}
