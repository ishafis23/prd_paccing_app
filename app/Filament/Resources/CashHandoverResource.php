<?php

namespace App\Filament\Resources;

use App\Enums\CashHandoverStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CashHandoverResource\Pages;
use App\Models\CashHandover;
use App\Models\User;
use App\Services\CashHandoverService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Verifikasi laporan setoran tunai teknisi. */
class CashHandoverResource extends BaseResource
{
    protected static ?string $model = CashHandover::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Setoran Tunai';

    protected static ?string $modelLabel = 'Setoran Tunai';

    protected static ?string $pluralModelLabel = 'Setoran Tunai';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]) ?? false;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['teknisi', 'order.customer', 'diterimaOleh']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Setoran Tunai')
                ->columns(3)
                ->schema([
                    TextEntry::make('tanggal_setor')->label('Tanggal setor')->date('d M Y'),
                    TextEntry::make('teknisi.name')->label('Teknisi'),
                    TextEntry::make('order_id')->label('Order')->state(fn (CashHandover $record): string => '#'.$record->order_id.' — '.($record->order?->customer?->nama ?? '—')),
                    TextEntry::make('nominal')->money('IDR', locale: 'id'),
                    TextEntry::make('diserahkan_ke')->label('Diserahkan ke'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (CashHandoverStatus $state): string => $state->label())->color(fn (CashHandoverStatus $state): string => self::warnaStatus($state)),
                    TextEntry::make('diterimaOleh.name')->label('Diterima oleh')->placeholder('—'),
                    TextEntry::make('diterima_pada')->label('Diverifikasi pada')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('catatan_verifikasi')->label('Catatan verifikasi')->placeholder('—'),
                    TextEntry::make('catatan')->label('Catatan teknisi')->columnSpanFull()->placeholder('—'),
                    ImageEntry::make('foto_path')->label('Bukti foto')->disk('public')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_setor', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_setor')->label('Tanggal setor')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('teknisi.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->state(fn (CashHandover $record): string => '#'.$record->order_id.' — '.($record->order?->customer?->nama ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->whereHas('order.customer', fn (Builder $customer) => $customer->where('nama', 'like', '%'.$search.'%'))
                        ->orWhere('order_id', 'like', '%'.$search.'%'))),
                Tables\Columns\TextColumn::make('nominal')->money('IDR', locale: 'id')->sortable(),
                Tables\Columns\TextColumn::make('diserahkan_ke')->label('Diserahkan ke')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (CashHandoverStatus $state): string => $state->label())->color(fn (CashHandoverStatus $state): string => self::warnaStatus($state)),
                Tables\Columns\TextColumn::make('diterimaOleh.name')->label('Diterima oleh')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(collect(CashHandoverStatus::cases())->mapWithKeys(fn (CashHandoverStatus $status): array => [$status->value => $status->label()])->all()),
                Tables\Filters\SelectFilter::make('teknisi_id')->label('Teknisi')->options(fn (): array => User::role(RoleName::Teknisi->value)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                Tables\Filters\Filter::make('tanggal_setor')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dari tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['dari'] ?? null), fn (Builder $q) => $q->whereDate('tanggal_setor', '>=', $data['dari']))
                        ->when(filled($data['sampai'] ?? null), fn (Builder $q) => $q->whereDate('tanggal_setor', '<=', $data['sampai'])))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        filled($data['dari'] ?? null) ? 'Dari: '.Carbon::parse($data['dari'])->format('d M Y') : null,
                        filled($data['sampai'] ?? null) ? 'Sampai: '.Carbon::parse($data['sampai'])->format('d M Y') : null,
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\Action::make('terima')
                    ->label('Terima')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (CashHandover $record): bool => $record->status === CashHandoverStatus::Menunggu)
                    ->requiresConfirmation()
                    ->action(fn (CashHandover $record) => self::verifikasi($record, true)),
                Tables\Actions\Action::make('tolak')
                    ->label('Tolak')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (CashHandover $record): bool => $record->status === CashHandoverStatus::Menunggu)
                    ->form([Forms\Components\Textarea::make('catatan')->label('Catatan penolakan')->required()->maxLength(2000)])
                    ->action(fn (CashHandover $record, array $data) => self::verifikasi($record, false, $data['catatan'])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashHandovers::route('/'),
            'view' => Pages\ViewCashHandover::route('/{record}'),
        ];
    }

    private static function verifikasi(CashHandover $record, bool $diterima, ?string $catatan = null): void
    {
        try {
            app(CashHandoverService::class)->verifikasi($record, auth()->user(), $diterima, $catatan);
            Notification::make()->success()->title($diterima ? 'Setoran diterima' : 'Setoran ditolak')->send();
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Gagal memverifikasi setoran')->body($e->getMessage())->send();
        }
    }

    private static function warnaStatus(CashHandoverStatus $status): string
    {
        return match ($status) {
            CashHandoverStatus::Menunggu => 'warning',
            CashHandoverStatus::Diterima => 'success',
            CashHandoverStatus::Ditolak => 'danger',
        };
    }
}
