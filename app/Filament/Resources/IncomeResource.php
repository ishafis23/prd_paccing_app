<?php

namespace App\Filament\Resources;

use App\Enums\IncomeCategory;
use App\Filament\Resources\IncomeResource\Pages;
use App\Models\Income;
use App\Support\EnumOptions;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Dibuat otomatis oleh PaymentService saat order lunas — read-only. */
class IncomeResource extends BaseResource
{
    protected static ?string $model = Income::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Finance';

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
                Tables\Columns\TextColumn::make('order.customer.nama')->label('Customer')->placeholder('—'),
                Tables\Columns\TextColumn::make('kategori')->badge(),
                Tables\Columns\TextColumn::make('nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('keterangan')->limit(40),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')->options(EnumOptions::for(IncomeCategory::class)),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncomes::route('/'),
        ];
    }
}
