<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tab "Riwayat Pelayanan" di Data Customer (revisi 14 Nov 2026 poin 19):
 * rincian tiap pelayanan — tanggal, layanan, keluhan, teknisi, status — untuk CRM/follow-up.
 */
class RiwayatPelayananRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Riwayat Pelayanan';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['serviceCatalog', 'teknisi', 'orderItems']))
            ->defaultSort('tanggal_jadwal', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order')->prefix('#'),
                Tables\Columns\TextColumn::make('tanggal_jadwal')->label('Tanggal')->date('d M Y')->sortable()->placeholder('-'),
                Tables\Columns\TextColumn::make('layanan')->label('Layanan')
                    ->state(fn (Order $record): string => $record->ringkasanLayanan())->wrap(),
                Tables\Columns\TextColumn::make('keluhan')->label('Keluhan')->wrap()->placeholder('-'),
                Tables\Columns\TextColumn::make('teknisi.name')->label('Teknisi')->placeholder('-'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('buka')
                    ->label('Buka Order')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
