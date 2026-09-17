<?php

namespace App\Filament\Resources\MotorCleaningResource\Pages;

use App\Filament\Resources\MotorCleaningResource;
use App\Models\MotorCleaning;
use App\Models\User;
use App\Services\MotorCleaningService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateMotorCleaning extends CreateRecord
{
    protected static string $resource = MotorCleaningResource::class;

    /**
     * Entri manual Admin/HR (dev-plan/15, B49) — dibuat langsung (bukan
     * lewat `MotorCleaningService::catat()`, yang mengasumsikan pencatat
     * adalah teknisi itu sendiri), lalu ledger insentif tiap teknisi
     * terpilih ditulis lewat `catatLedgerUntuk()` supaya konsisten dengan
     * alur self-service.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $teknisiIds = $data['teknisi_ids'];
        $tanggal = Carbon::parse($data['tanggal']);

        $record = MotorCleaning::create([
            'tanggal' => $tanggal->toDateString(),
            'foto' => $data['foto'],
            'dicatat_oleh' => auth()->id(),
        ]);

        $record->teknisis()->sync($teknisiIds);

        $service = app(MotorCleaningService::class);
        foreach ($teknisiIds as $id) {
            $service->catatLedgerUntuk(User::findOrFail($id), $tanggal, $record->foto, $record->id);
        }

        return $record;
    }
}
