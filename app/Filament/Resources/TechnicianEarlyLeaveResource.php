<?php

namespace App\Filament\Resources;

use App\Enums\EarlyLeaveStatus;
use App\Enums\RoleName;
use App\Filament\Resources\TechnicianEarlyLeaveResource\Pages;
use App\Models\TechnicianEarlyLeave;
use App\Services\EarlyLeaveService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * SDM/HRD → "Pulang Cepat" (dev-plan/hrd/02): verifikasi pengajuan pulang
 * cepat teknisi. Yang disetujui dihitung setengah hari di penggajian.
 */
class TechnicianEarlyLeaveResource extends BaseResource
{
    protected static ?string $model = TechnicianEarlyLeave::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Pulang Cepat';

    protected static ?string $modelLabel = 'Pulang Cepat';

    protected static ?string $pluralModelLabel = 'Pulang Cepat';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Hr->value,
            RoleName::Admin->value,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->formatStateUsing(fn ($state): string => $state ? Carbon::parse($state)->format('H:i') : '—'),
                Tables\Columns\TextColumn::make('alasan')->limit(40)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (EarlyLeaveStatus $state): string => match ($state) {
                        EarlyLeaveStatus::Disetujui => 'success',
                        EarlyLeaveStatus::Menunggu => 'warning',
                        EarlyLeaveStatus::Ditolak => 'danger',
                    }),
                Tables\Columns\TextColumn::make('diverifikasiOleh.name')->label('Diverifikasi oleh')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        EarlyLeaveStatus::Menunggu->value => 'Menunggu',
                        EarlyLeaveStatus::Disetujui->value => 'Disetujui',
                        EarlyLeaveStatus::Ditolak->value => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TechnicianEarlyLeave $r): bool => $r->status !== EarlyLeaveStatus::Disetujui && static::canViewAny())
                    ->requiresConfirmation()
                    ->action(function (TechnicianEarlyLeave $record): void {
                        app(EarlyLeaveService::class)->setujui($record, auth()->user());
                        Notification::make()->success()->title('Pulang cepat disetujui')->send();
                    }),
                Tables\Actions\Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TechnicianEarlyLeave $r): bool => $r->status !== EarlyLeaveStatus::Ditolak && static::canViewAny())
                    ->form([
                        Forms\Components\Textarea::make('catatan')->label('Alasan penolakan')->rows(2),
                    ])
                    ->action(function (TechnicianEarlyLeave $record, array $data): void {
                        app(EarlyLeaveService::class)->tolak($record, auth()->user(), $data['catatan'] ?? null);
                        Notification::make()->success()->title('Pulang cepat ditolak')->send();
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianEarlyLeaves::route('/'),
        ];
    }
}
