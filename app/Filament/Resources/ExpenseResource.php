<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseCategory;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('kategori')
                    ->options(EnumOptions::for(ExpenseCategory::class))
                    ->required(),
                Forms\Components\TextInput::make('nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(1),
                Forms\Components\DatePicker::make('tanggal')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('bukti')
                    ->label('Bukti/Struk')
                    ->image()
                    ->directory('expenses'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('kategori')->badge(),
                Tables\Columns\TextColumn::make('nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('keterangan')->limit(40),
                Tables\Columns\TextColumn::make('recordedBy.name')->label('Dicatat oleh'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')->options(EnumOptions::for(ExpenseCategory::class)),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
