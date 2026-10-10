<?php

namespace App\Filament\Resources;

use App\Enums\ServiceType;
use App\Enums\UnitType;
use App\Filament\Resources\ServiceCatalogResource\Pages;
use App\Models\ServiceCatalog;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceCatalogResource extends BaseResource
{
    protected static ?string $model = ServiceCatalog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Katalog Layanan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('jenis_layanan')
                    ->options(EnumOptions::for(ServiceType::class))
                    ->required(),
                Forms\Components\Select::make('jenis_unit')
                    ->options(EnumOptions::for(UnitType::class)),
                Forms\Components\TextInput::make('pk')
                    ->label('Spesifikasi PK')
                    ->maxLength(255),
                Forms\Components\TextInput::make('harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                Forms\Components\Select::make('mode_omset')
                    ->label('Masuk Omset')
                    ->options([
                        'otomatis' => 'Otomatis (Cuci/Service = Jasa, lainnya = Material)',
                        'jasa' => 'Selalu Jasa',
                        'material' => 'Selalu Material',
                    ])
                    ->default('otomatis')
                    ->required()
                    ->helperText('Dipakai saat baris order dibuat dari katalog ini. Bisa diubah per baris di detail order.'),
                Forms\Components\TextInput::make('interval_bulan')
                    ->label('Interval Reminder (bulan)')
                    ->helperText('Kosongkan jika layanan ini tidak perlu reminder servis berikutnya (mis. pengadaan AC).')
                    ->numeric(),
                Forms\Components\Toggle::make('aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('jenis_layanan')->badge()->sortable(),
                Tables\Columns\TextColumn::make('jenis_unit')->sortable(),
                Tables\Columns\TextColumn::make('pk')->label('PK'),
                Tables\Columns\TextColumn::make('harga')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('mode_omset')->label('Omset')->badge()->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'otomatis')),
                Tables\Columns\TextColumn::make('interval_bulan')->label('Interval (bln)'),
                Tables\Columns\IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis_layanan')->options(EnumOptions::for(ServiceType::class)),
                Tables\Filters\TernaryFilter::make('aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCatalogs::route('/'),
            'create' => Pages\CreateServiceCatalog::route('/create'),
            'edit' => Pages\EditServiceCatalog::route('/{record}/edit'),
        ];
    }
}
