<?php

namespace App\Filament\Resources;

use App\Enums\IncomeCategory;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Filament\Resources\ServiceTypeOptionResource\Pages;
use App\Models\ServiceTypeOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Customer & Order → "Jenis Layanan" (dev-plan/26): menambah jenis layanan
 * TAMBAHAN (melengkapi enum ServiceType bawaan yang read-only). Jenis baru ini
 * muncul di opsi select Katalog/Order/Teknisi dan laporan omset.
 */
class ServiceTypeOptionResource extends BaseResource
{
    protected static ?string $model = ServiceTypeOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Jenis Layanan';

    protected static ?string $modelLabel = 'Jenis Layanan';

    protected static ?string $pluralModelLabel = 'Jenis Layanan';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
        ]) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('Nama Jenis Layanan')
                ->placeholder('mis. Ganti Kompresor')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('kode')
                ->label('Kode (unik)')
                ->helperText('Huruf kecil & underscore, dipakai sistem — mis. ganti_kompresor. Tidak boleh sama dengan jenis bawaan.')
                ->required()
                ->maxLength(60)
                ->rule('regex:/^[a-z0-9_]+$/')
                ->unique(ignoreRecord: true)
                ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                    if (ServiceType::tryFrom((string) $value) !== null) {
                        $fail('Kode ini dipakai jenis bawaan — pakai kode lain.');
                    }
                }),
            Forms\Components\Select::make('komponen')
                ->label('Masuk Omset')
                ->options([
                    IncomeCategory::Jasa->value => 'Jasa',
                    IncomeCategory::Material->value => 'Material',
                ])
                ->default(IncomeCategory::Jasa->value)
                ->required(),
            Forms\Components\TextInput::make('urutan')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('aktif')
                ->label('Aktif (tampil di pilihan)')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kode')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('komponen')
                    ->badge()
                    ->color(fn (IncomeCategory $state): string => $state === IncomeCategory::Jasa ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('urutan')->sortable(),
                Tables\Columns\ToggleColumn::make('aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('urutan');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceTypeOptions::route('/'),
            'create' => Pages\CreateServiceTypeOption::route('/create'),
            'edit' => Pages\EditServiceTypeOption::route('/{record}/edit'),
        ];
    }
}
