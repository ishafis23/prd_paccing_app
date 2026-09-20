<?php

namespace App\Filament\Resources;

use App\Enums\StockCategory;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\StockItemResource\Pages;
use App\Models\StockItem;
use App\Services\StockService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;

class StockItemResource extends BaseResource
{
    protected static ?string $model = StockItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Stok';

    protected static ?string $navigationLabel = 'Barang & Stok';

    public static function getNavigationBadge(): ?string
    {
        return (string) StockItem::menipis()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_barang')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('kategori')
                    ->options(EnumOptions::for(StockCategory::class))
                    ->required(),
                Forms\Components\TextInput::make('satuan')
                    ->default('pcs')
                    ->required(),
                Forms\Components\TextInput::make('stok_minimum')
                    ->numeric()
                    ->default(0)
                    ->helperText('Item ditandai "menipis" kalau stok saat ini <= nilai ini.'),
                Forms\Components\TextInput::make('harga_beli')
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\Toggle::make('aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_barang')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kategori')->badge(),
                Tables\Columns\TextColumn::make('stok_saat_ini')
                    ->label('Stok')
                    ->sortable()
                    ->color(fn (StockItem $record) => $record->stok_saat_ini <= $record->stok_minimum ? 'danger' : 'success')
                    ->suffix(fn (StockItem $record) => ' '.$record->satuan),
                Tables\Columns\TextColumn::make('stok_minimum')->label('Min'),
                Tables\Columns\IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')->options(EnumOptions::for(StockCategory::class)),
                Tables\Filters\Filter::make('menipis')
                    ->label('Stok Menipis')
                    ->query(fn ($query) => $query->menipis()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('stokMasuk')
                    ->label('Stok Masuk')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('jumlah')->numeric()->required()->minValue(1),
                        Forms\Components\Textarea::make('keterangan'),
                    ])
                    ->action(function (StockItem $record, array $data) {
                        try {
                            app(StockService::class)->masuk($record, (int) $data['jumlah'], auth()->user(), $data['keterangan'] ?? null);
                            Notification::make()->success()->title('Stok masuk tercatat')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal mencatat stok masuk')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('penyesuaian')
                    ->label('Penyesuaian')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('delta')
                            ->numeric()
                            ->required()
                            ->helperText('Boleh negatif (mis. -2 untuk mengurangi 2 dari stok opname).'),
                        Forms\Components\Textarea::make('keterangan'),
                    ])
                    ->action(function (StockItem $record, array $data) {
                        try {
                            app(StockService::class)->penyesuaian($record, (int) $data['delta'], auth()->user(), $data['keterangan'] ?? null);
                            Notification::make()->success()->title('Penyesuaian stok tercatat')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal menyesuaikan stok')->body($e->getMessage())->send();
                        }
                    }),
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
            'index' => Pages\ListStockItems::route('/'),
            'create' => Pages\CreateStockItem::route('/create'),
            'edit' => Pages\EditStockItem::route('/{record}/edit'),
        ];
    }
}
