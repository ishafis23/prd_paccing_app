<?php

namespace App\Filament\Resources;

use App\Enums\DailyAttendanceStatus;
use App\Enums\RoleName;
use App\Filament\Resources\DailyAttendanceResource\Pages;
use App\Models\DailyAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Menu Absensi & Insentif → "Rekap Absensi" (dev-plan/15, §4/§5): daftar
 * absen kantor harian teknisi + override pengecualian denda (B45).
 */
class DailyAttendanceResource extends Resource
{
    protected static ?string $model = DailyAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Rekap Absensi';

    protected static ?string $modelLabel = 'Absensi';

    protected static ?string $pluralModelLabel = 'Rekap Absensi';

    private const PENGELOLA_ROLES = [RoleName::Owner, RoleName::Admin, RoleName::Hr];

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Hr->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // baris dibuat teknisi lewat scan, bukan form admin.
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::PENGELOLA_ROLES)) ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Toggle::make('dikecualikan_denda')
                ->label('Kecualikan dari denda telat')
                ->helperText('Menolak entri denda telat hari itu di ledger insentif (B55) — efek nyata ke total gaji.'),
            Forms\Components\Textarea::make('catatan_admin')
                ->label('Catatan')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Teknisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('foto_datang')
                    ->label('Foto Datang')
                    ->disk('public')
                    ->size(50)
                    ->square(),
                Tables\Columns\TextColumn::make('jam_datang')
                    ->label('Jam Datang')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status_datang')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?DailyAttendanceStatus $state): string => match ($state) {
                        DailyAttendanceStatus::Bonus => 'success',
                        DailyAttendanceStatus::Normal => 'gray',
                        DailyAttendanceStatus::TelatToleransi => 'warning',
                        DailyAttendanceStatus::Telat => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\ImageColumn::make('foto_pulang')
                    ->label('Foto Pulang')
                    ->disk('public')
                    ->size(50)
                    ->square(),
                Tables\Columns\TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->dateTime('H:i')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('dikecualikan_denda')
                    ->label('Dikecualikan')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status_datang')
                    ->label('Status')
                    ->options(EnumOptions::for(DailyAttendanceStatus::class)),
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('dari'),
                        Forms\Components\DatePicker::make('sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
                            ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => static::canEdit(null))
                    ->using(function (DailyAttendance $record, array $data): DailyAttendance {
                        return app(AttendanceService::class)->kecualikanDenda(
                            $record,
                            (bool) $data['dikecualikan_denda'],
                            auth()->user(),
                            $data['catatan_admin'] ?? null,
                        );
                    })
                    ->after(function (): void {
                        Notification::make()->title('Absensi diperbarui')->success()->send();
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyAttendances::route('/'),
        ];
    }
}
