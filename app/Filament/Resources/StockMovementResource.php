<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Support\EnumOptions;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Stok';

    protected static ?string $navigationLabel = 'Kartu Stok';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('stockItem.nama_barang')->label('Barang')->searchable(),
                Tables\Columns\TextColumn::make('jenis')->badge()->color(fn (MovementType $state) => match ($state) {
                    MovementType::Masuk => 'success',
                    MovementType::Keluar => 'danger',
                    MovementType::Penyesuaian => 'warning',
                }),
                Tables\Columns\TextColumn::make('jumlah'),
                Tables\Columns\TextColumn::make('referensi')->placeholder('—'),
                Tables\Columns\TextColumn::make('recordedBy.name')->label('Dicatat oleh'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')->options(EnumOptions::for(MovementType::class)),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
