<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\ProviderAccountResource\Pages;
use App\Models\ProviderAccount;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * dev-plan/23 §5.1: Akun Gateway WhatsApp (mis. wablasted.com). `iduser`
 * adalah identitas akun di provider — dipakai sebagai parameter kirim.
 * Base URL boleh dikosongkan agar memakai nilai `.env` (config/wa.php).
 */
class ProviderAccountResource extends BaseResource
{
    protected static ?string $model = ProviderAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Akun Gateway';

    protected static ?string $modelLabel = 'Akun Gateway';

    protected static ?string $pluralModelLabel = 'Akun Gateway';

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
                Forms\Components\TextInput::make('label')
                    ->label('Nama Akun')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Nama bebas untuk akun ini, mis. "Wablasted Sekolah".'),
                Forms\Components\TextInput::make('iduser')
                    ->label('ID User (iduser)')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('ID akun di gateway/provider (parameter "iduser" saat kirim). Ambil dari panel wablasted atau tabel provider_accounts di arm_app — contoh: 397.'),

                Section::make('Base URL (opsional)')
                    ->description('Kosongkan agar memakai nilai .env (WA_BASE_URL_*). Isi hanya bila akun ini memakai server/endpoint berbeda.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('base_url_qr')
                            ->label('Base URL QR')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://notiz.wablasted.com'),
                        Forms\Components\TextInput::make('base_url_send')
                            ->label('Base URL Kirim')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://notiz.wablasted.com'),
                        Forms\Components\TextInput::make('base_url_group')
                            ->label('Base URL Grup')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://aplikasi.wablasted.com'),
                        Forms\Components\TextInput::make('base_url_document')
                            ->label('Base URL Dokumen (bridge)')
                            ->maxLength(255)
                            ->placeholder('https://.../apiwablasted/api_document.php')
                            ->helperText('Harus diakhiri .php bila memakai bridge JSON.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Nama Akun')
                    ->searchable(),
                Tables\Columns\TextColumn::make('iduser')
                    ->label('ID User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('devices_count')
                    ->label('Jumlah Device')
                    ->counts('devices'),
                Tables\Columns\TextColumn::make('base_url_send')
                    ->label('Base URL Kirim')
                    ->placeholder('(dari .env)')
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderAccounts::route('/'),
            'create' => Pages\CreateProviderAccount::route('/create'),
            'edit' => Pages\EditProviderAccount::route('/{record}/edit'),
        ];
    }
}
