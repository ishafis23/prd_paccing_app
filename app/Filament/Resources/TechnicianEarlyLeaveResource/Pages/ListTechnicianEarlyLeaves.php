<?php

namespace App\Filament\Resources\TechnicianEarlyLeaveResource\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\TechnicianEarlyLeaveResource;
use App\Models\User;
use App\Services\EarlyLeaveService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTechnicianEarlyLeaves extends ListRecords
{
    protected static string $resource = TechnicianEarlyLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('input')
                ->label('Input Pulang Cepat Teknisi')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => TechnicianEarlyLeaveResource::canViewAny())
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Teknisi')
                        ->options(fn () => User::role(RoleName::Teknisi->value)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('tanggal')->default(now())->maxDate(now())->required(),
                    Forms\Components\TimePicker::make('jam_pulang')->label('Jam Pulang / Masuk')->seconds(false)->required(),
                    Forms\Components\TextInput::make('nominal_potongan')
                        ->label('Nominal Potongan')
                        ->numeric()->prefix('Rp')->minValue(0)
                        ->helperText('Kosongkan untuk default (0,5 × potongan OFF/hari).'),
                    Forms\Components\Textarea::make('alasan')->rows(2),
                ])
                ->action(function (array $data): void {
                    app(EarlyLeaveService::class)->inputOlehAdmin(
                        auth()->user(),
                        (int) $data['user_id'],
                        $data['tanggal'],
                        $data['jam_pulang'],
                        $data['alasan'] ?? null,
                        filled($data['nominal_potongan'] ?? null) ? (float) $data['nominal_potongan'] : null,
                    );
                    Notification::make()->success()->title('Pulang cepat dicatat & disetujui')->send();
                }),
        ];
    }
}
