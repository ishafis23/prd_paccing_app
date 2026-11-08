<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Filament\Tables;
use Filament\Tables\Table;

/** Riwayat Perubahan (audit trail) — read-only, hanya Owner/Admin. */
class AuditLogResource extends BaseResource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Riwayat Perubahan';

    protected static ?string $modelLabel = 'Riwayat Perubahan';

    protected static ?string $pluralModelLabel = 'Riwayat Perubahan';

    public const AKSI = [
        AuditLogService::PAYMENT_CATAT => 'Catat pembayaran',
        AuditLogService::PAYMENT_SINKRON_TOTAL => 'Sinkron total tagihan',
        AuditLogService::PAYMENT_EDIT_OMSET => 'Edit omset',
        AuditLogService::ORDER_KOREKSI_TOTAL => 'Koreksi total order',
    ];

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
        $format = fn (?array $d): string => $d === null ? '-' : collect($d)
            ->map(fn ($v, $k) => $k.': '.(is_numeric($v) ? number_format((float) $v, 0, ',', '.') : ($v ?? '-')))
            ->implode(' · ');

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Pelaku')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('aksi')->label('Aksi')->badge()
                    ->formatStateUsing(fn (string $state): string => self::AKSI[$state] ?? $state),
                Tables\Columns\TextColumn::make('order_id')->label('Order')->prefix('#')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('sebelum')->label('Sebelum')->formatStateUsing(fn ($state) => $format(is_array($state) ? $state : null))->wrap(),
                Tables\Columns\TextColumn::make('sesudah')->label('Sesudah')->formatStateUsing(fn ($state) => $format(is_array($state) ? $state : null))->wrap(),
                Tables\Columns\TextColumn::make('keterangan')->label('Keterangan')->limit(60)->tooltip(fn (?string $state): ?string => $state)->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('aksi')->label('Aksi')->options(self::AKSI),
                Tables\Filters\SelectFilter::make('user_id')->label('Pelaku')->relationship('user', 'name'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
