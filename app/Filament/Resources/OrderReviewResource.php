<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\OrderReviewResource\Pages;
use App\Models\OrderReview;
use Filament\Tables;
use Filament\Tables\Table;

/** Ulasan customer atas pekerjaan teknisi (revisi 14 Nov 2026 poin 8) — read-only. */
class OrderReviewResource extends BaseResource
{
    protected static ?string $model = OrderReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Ulasan Customer';

    protected static ?string $modelLabel = 'Ulasan';

    protected static ?string $pluralModelLabel = 'Ulasan Customer';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['order.customer', 'teknisi']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('order_id')->label('Order')->prefix('#')->searchable(),
                Tables\Columns\TextColumn::make('order.customer.nama')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('teknisi.name')->label('Teknisi')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('rating')->label('Penilaian')->sortable()
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('komentar')->label('Komentar')->limit(80)->tooltip(fn (?string $state): ?string => $state)->wrap()->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teknisi_id')->label('Teknisi')->relationship('teknisi', 'name'),
                Tables\Filters\SelectFilter::make('rating')->label('Penilaian')
                    ->options([1 => '1 bintang', 2 => '2 bintang', 3 => '3 bintang', 4 => '4 bintang', 5 => '5 bintang']),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrderReviews::route('/')];
    }
}
