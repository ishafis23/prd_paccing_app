<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\WaDeviceResource\Pages;
use App\Models\WaDevice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * dev-plan/23 fase 23-A: kelola device WhatsApp — tambah, atur provider,
 * tugaskan ke user (assign), lepas, hapus. Untuk menyambungkan QR & uji
 * koneksi, lihat halaman "Perangkat & Tes WA".
 */
class WaDeviceResource extends BaseResource
{
    protected static ?string $model = WaDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Perangkat WhatsApp';

    protected static ?string $modelLabel = 'Perangkat WhatsApp';

    protected static ?string $pluralModelLabel = 'Perangkat WhatsApp';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('provider_account_id')
                    ->label('Akun Gateway')
                    ->relationship('providerAccount', 'label')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('device_name')
                    ->label('Nama Device')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('Harus sama dengan nama device di dashboard provider (mis. nurulfikri3).'),
                Forms\Components\TextInput::make('nomor_wa')
                    ->label('Nomor WA')
                    ->tel()
                    ->maxLength(20)
                    ->placeholder('62812xxxxxxx'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        WaDevice::STATUS_TERSEDIA => 'Tersedia',
                        WaDevice::STATUS_TERPAKAI => 'Terpakai',
                        WaDevice::STATUS_TERPUTUS => 'Terputus',
                    ])
                    ->default(WaDevice::STATUS_TERSEDIA)
                    ->required(),
                Forms\Components\Select::make('assigned_user_id')
                    ->label('Ditugaskan ke User')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('-- Belum ditugaskan --'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('providerAccount.label')
                    ->label('Akun Gateway')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('nomor_wa')
                    ->label('Nomor WA')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WaDevice::STATUS_TERPAKAI => 'info',
                        WaDevice::STATUS_TERPUTUS => 'danger',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Koneksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === WaDevice::KONEKSI_TERHUBUNG ? 'Terhubung' : 'Belum')
                    ->color(fn (string $state): string => $state === WaDevice::KONEKSI_TERHUBUNG ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Pemakai')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('last_checked_at')
                    ->label('Cek Terakhir')
                    ->since()
                    ->placeholder('-'),
            ])
            ->defaultSort('device_name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WaDevice::STATUS_TERSEDIA => 'Tersedia',
                        WaDevice::STATUS_TERPAKAI => 'Terpakai',
                        WaDevice::STATUS_TERPUTUS => 'Terputus',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaDevices::route('/'),
            'create' => Pages\CreateWaDevice::route('/create'),
            'edit' => Pages\EditWaDevice::route('/{record}/edit'),
        ];
    }
}
