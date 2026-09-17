<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\MotorCleaningResource\Pages;
use App\Models\MotorCleaning;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Menu Absensi & Insentif → "Cuci Motor" (dev-plan/15, Games 3 — B49):
 * read + tambah manual (Admin/HR); pencatatan utama tetap lewat teknisi
 * di menu Absensi.
 */
class MotorCleaningResource extends Resource
{
    protected static ?string $model = MotorCleaning::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Cuci Motor';

    protected static ?string $modelLabel = 'Cuci Motor';

    protected static ?string $pluralModelLabel = 'Cuci Motor';

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

    public static function canCreate(): bool
    {
        return static::bolehKelola();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('teknisi_ids')
                ->label('Teknisi (maks. 2)')
                ->multiple()
                ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->maxItems(2),
            Forms\Components\DatePicker::make('tanggal')
                ->required()
                ->default(now()),
            Forms\Components\FileUpload::make('foto')
                ->label('Foto Bukti')
                ->image()
                ->disk('public')
                ->directory('absensi')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('teknisis.name')
                    ->label('Teknisi')
                    ->badge(),
                Tables\Columns\ImageColumn::make('foto')
                    ->label('Foto')
                    ->disk('public')
                    ->size(50)
                    ->square(),
                Tables\Columns\TextColumn::make('dicatatOleh.name')
                    ->label('Dicatat oleh'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime('d M Y H:i'),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotorCleanings::route('/'),
            'create' => Pages\CreateMotorCleaning::route('/create'),
        ];
    }
}
