<?php

namespace App\Filament\Resources;

use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\RoleName;
use App\Filament\Resources\TechnicianIncentiveResource\Pages;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\TechnicianIncentiveService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Menu Absensi & Insentif → "Rekap Insentif" (dev-plan/15, §4/§5): ledger
 * `technician_incentives` — entri otomatis (Games 1/4, denda telat) + entri
 * manual Admin/HR (Games 5/6 sementara, B53) + verifikasi foto (B55).
 */
class TechnicianIncentiveResource extends BaseResource
{
    protected static ?string $model = TechnicianIncentive::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Rekap Insentif';

    protected static ?string $modelLabel = 'Insentif';

    protected static ?string $pluralModelLabel = 'Rekap Insentif';

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
        return false; // lewat aksi "Tambah Entri Manual", bukan form create standar.
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function bolehKelola(): bool
    {
        return auth()->user()?->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::PENGELOLA_ROLES)) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
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
                Tables\Columns\TextColumn::make('kategori')
                    ->badge(),
                Tables\Columns\TextColumn::make('tipe')
                    ->badge()
                    ->color(fn (IncentiveTipe $state): string => $state === IncentiveTipe::Bonus ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('nominal')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('IDR')),
                Tables\Columns\ImageColumn::make('foto_bukti')
                    ->label('Foto')
                    ->disk('public')
                    ->size(50)
                    ->square(),
                Tables\Columns\TextColumn::make('sumber')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status_verifikasi')
                    ->label('Verifikasi')
                    ->badge()
                    ->color(fn (IncentiveStatusVerifikasi $state): string => match ($state) {
                        IncentiveStatusVerifikasi::Disetujui => 'success',
                        IncentiveStatusVerifikasi::Menunggu => 'warning',
                        IncentiveStatusVerifikasi::Ditolak => 'danger',
                    }),
                Tables\Columns\TextColumn::make('catatan')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('kategori')
                    ->options(EnumOptions::for(IncentiveKategori::class)),
                Tables\Filters\SelectFilter::make('status_verifikasi')
                    ->label('Verifikasi')
                    ->options(EnumOptions::for(IncentiveStatusVerifikasi::class)),
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
            ->headerActions([
                Tables\Actions\Action::make('tambahManual')
                    ->label('Tambah Entri Manual')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::bolehKelola())
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Teknisi')
                            ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\DatePicker::make('tanggal')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('kategori')
                            ->options(EnumOptions::for(IncentiveKategori::class))
                            ->required(),
                        Forms\Components\Select::make('tipe')
                            ->options(EnumOptions::for(IncentiveTipe::class))
                            ->default(IncentiveTipe::Bonus->value)
                            ->required(),
                        Forms\Components\TextInput::make('nominal')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\Textarea::make('catatan')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        app(TechnicianIncentiveService::class)->catatManual(
                            User::findOrFail($data['user_id']),
                            Carbon::parse($data['tanggal']),
                            IncentiveKategori::from($data['kategori']),
                            IncentiveTipe::from($data['tipe']),
                            (float) $data['nominal'],
                            auth()->user(),
                            $data['catatan'] ?? null,
                        );

                        Notification::make()->title('Entri insentif dicatat')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TechnicianIncentive $record): bool => static::bolehKelola() && $record->status_verifikasi !== IncentiveStatusVerifikasi::Disetujui)
                    ->requiresConfirmation()
                    ->action(function (TechnicianIncentive $record): void {
                        app(TechnicianIncentiveService::class)->setujui($record, auth()->user());

                        Notification::make()->title('Entri disetujui')->success()->send();
                    }),
                Tables\Actions\Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TechnicianIncentive $record): bool => static::bolehKelola() && $record->status_verifikasi !== IncentiveStatusVerifikasi::Ditolak)
                    ->form([
                        Forms\Components\Textarea::make('alasan')
                            ->label('Alasan penolakan')
                            ->rows(2),
                    ])
                    ->action(function (TechnicianIncentive $record, array $data): void {
                        app(TechnicianIncentiveService::class)->tolak($record, auth()->user(), $data['alasan'] ?? null);

                        Notification::make()->title('Entri ditolak')->success()->send();
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianIncentives::route('/'),
        ];
    }
}
