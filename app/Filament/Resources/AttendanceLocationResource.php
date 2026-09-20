<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\AttendanceLocationResource\Pages;
use App\Models\AttendanceLocation;
use App\Services\AttendanceLocationService;
use App\Services\GoogleMapsLinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Menu Absensi & Insentif → "Lokasi Absensi" (dev-plan/19): titik kantor
 * utk absensi mode GPS — bisa lebih dari 1 aktif sekaligus (B70), absen
 * sah kalau teknisi dalam radius SALAH SATU lokasi aktif.
 */
class AttendanceLocationResource extends BaseResource
{
    protected static ?string $model = AttendanceLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Lokasi Absensi';

    protected static ?string $modelLabel = 'Lokasi Absensi';

    protected static ?string $pluralModelLabel = 'Lokasi Absensi';

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
        return false; // lewat aksi "Tambah Lokasi", bukan form create standar.
    }

    public static function canEdit($record): bool
    {
        return static::bolehKelola();
    }

    public static function canDelete($record): bool
    {
        return static::bolehKelola();
    }

    public static function bolehKelola(): bool
    {
        return auth()->user()?->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::PENGELOLA_ROLES)) ?? false;
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function formSchema(): array
    {
        return [
            Forms\Components\TextInput::make('nama')
                ->label('Nama Lokasi')
                ->placeholder('mis. Kantor Pusat, Gudang')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('radius_meter')
                ->label('Radius (meter)')
                ->helperText('Teknisi dianggap "di lokasi" kalau jaraknya dalam radius ini.')
                ->numeric()
                ->minValue(10)
                ->default(50)
                ->required(),
            Forms\Components\Fieldset::make('Koordinat')
                ->columnSpanFull()
                ->schema([
                    Forms\Components\TextInput::make('maps_link')
                        ->label('Link Google Maps')
                        ->placeholder('Tempel link dari WhatsApp/Google Maps di sini')
                        ->helperText('Tempel link lalu klik "Ambil Koordinat", atau isi/geser pin manual di peta.')
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('ambilKoordinat')
                                ->label('Ambil Koordinat')
                                ->icon('heroicon-m-map-pin')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $link = trim((string) $get('maps_link'));

                                    if ($link === '') {
                                        Notification::make()
                                            ->title('Isi link Google Maps dulu.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    try {
                                        $coords = app(GoogleMapsLinkService::class)->resolveCoordinates($link);
                                    } catch (BusinessRuleException $e) {
                                        Notification::make()
                                            ->title($e->getMessage())
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $set('latitude', $coords['lat']);
                                    $set('longitude', $coords['lng']);

                                    Notification::make()
                                        ->title('Koordinat berhasil diambil. Cek pin di peta di bawah.')
                                        ->success()
                                        ->send();
                                })
                        ),
                    Forms\Components\TextInput::make('latitude')
                        ->required()
                        ->numeric()
                        ->step('0.0000001')
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('longitude')
                        ->required()
                        ->numeric()
                        ->step('0.0000001')
                        ->live(onBlur: true),
                    Forms\Components\ViewField::make('peta_lokasi')
                        ->label('Peta (klik/geser pin untuk koreksi titik)')
                        ->columnSpanFull()
                        ->dehydrated(false)
                        ->view('filament.forms.components.lokasi-picker')
                        ->viewData(function (Forms\Components\ViewField $component) {
                            $get = $component->getGetCallback();

                            return [
                                'lat' => $get('latitude'),
                                'lng' => $get('longitude'),
                                'latPath' => $component->generateRelativeStatePath('latitude'),
                                'lngPath' => $component->generateRelativeStatePath('longitude'),
                            ];
                        }),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama'),
                Tables\Columns\TextColumn::make('latitude')
                    ->label('Koordinat')
                    ->formatStateUsing(fn ($record) => $record->latitude !== null ? "{$record->latitude}, {$record->longitude}" : '—'),
                Tables\Columns\TextColumn::make('radius_meter')
                    ->label('Radius')
                    ->formatStateUsing(fn (int $state): string => "{$state} m"),
                Tables\Columns\ToggleColumn::make('aktif')
                    ->disabled(fn (): bool => ! static::bolehKelola()),
                Tables\Columns\TextColumn::make('dibuatOleh.name')
                    ->label('Dibuat oleh'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tambahLokasi')
                    ->label('Tambah Lokasi')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::bolehKelola())
                    ->form(static::formSchema())
                    ->action(function (array $data): void {
                        app(AttendanceLocationService::class)->tambah($data, auth()->user());

                        Notification::make()->title('Lokasi ditambahkan')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => static::bolehKelola())
                    ->form(static::formSchema())
                    ->using(function (AttendanceLocation $record, array $data): AttendanceLocation {
                        return app(AttendanceLocationService::class)->perbarui($record, $data, auth()->user());
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => static::bolehKelola())
                    ->using(function (AttendanceLocation $record): void {
                        app(AttendanceLocationService::class)->hapus($record, auth()->user());
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceLocations::route('/'),
        ];
    }
}
