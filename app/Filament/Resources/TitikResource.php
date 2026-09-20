<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TitikResource\Pages;
use App\Models\Titik;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master data slot jam kunjungan baku (dev-plan/18) — dipakai sbg pilihan
 * "Titik/Jam Kunjungan" di wizard Create Order, gantikan input jam manual.
 */
class TitikResource extends BaseResource
{
    protected static ?string $model = Titik::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Titik Jadwal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TimePicker::make('jam')
                    ->required()
                    ->seconds(false),
                Forms\Components\TextInput::make('urutan')
                    ->numeric()
                    ->default(0)
                    ->helperText('Urutan tampil di pilihan — bisa juga diubah lewat drag di daftar.'),
                Forms\Components\Toggle::make('aktif')
                    ->default(true)
                    ->helperText('Titik nonaktif tidak muncul di pilihan wizard Create Order.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('urutan')->sortable(),
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jam')->time('H:i')->sortable(),
                Tables\Columns\IconColumn::make('aktif')->boolean(),
            ])
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->filters([
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
            'index' => Pages\ListTitiks::route('/'),
            'create' => Pages\CreateTitik::route('/create'),
            'edit' => Pages\EditTitik::route('/{record}/edit'),
        ];
    }
}
