<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\EnumOptions;
use App\Support\Url;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Daftar Invoice (dev-plan/21 §7). Dibuat lewat aksi "Buat Invoice" di Order
 * atau "Buat Invoice (Multi-Order)" di halaman ini — tidak ada form create
 * bebas. Owner/Admin/Finance saja; teknisi tidak punya akses panel.
 */
class InvoiceResource extends BaseResource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Invoice';

    protected static ?string $modelLabel = 'Invoice';

    public static function canViewAny(): bool
    {
        return InvoiceService::boleh(auth()->user());
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
        return parent::getEloquentQuery()->with(['customer', 'orders.payments']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Invoice')
                ->columns(3)
                ->schema([
                    TextEntry::make('nomor')->label('No. Invoice')->weight('bold'),
                    TextEntry::make('customer.nama')->label('Customer'),
                    TextEntry::make('status')->badge()->color(fn (InvoiceStatus $state): string => self::warnaStatus($state)),
                    TextEntry::make('tanggal')->date('d M Y'),
                    TextEntry::make('jatuh_tempo')->date('d M Y')
                        ->color(fn (Invoice $record): ?string => $record->lewatJatuhTempo() ? 'danger' : null)
                        ->suffix(fn (Invoice $record): string => $record->lewatJatuhTempo() ? ' — LEWAT JATUH TEMPO' : ''),
                    TextEntry::make('catatan')->placeholder('—'),
                    TextEntry::make('total')->label('Total')->state(fn (Invoice $record): string => self::rp((float) $record->total)),
                    TextEntry::make('dibayar')->label('Sudah dibayar')->state(fn (Invoice $record): string => self::rp($record->totalDibayar())),
                    TextEntry::make('saldo')->label('Saldo jatuh tempo')->state(fn (Invoice $record): string => self::rp($record->saldo()))->weight('bold'),
                    TextEntry::make('rekening')
                        ->label('Rekening (snapshot)')
                        ->columnSpanFull()
                        ->state(fn (Invoice $record): string => filled($record->bank_nama.$record->bank_rekening.$record->bank_atas_nama)
                            ? trim($record->bank_nama.' '.$record->bank_rekening.($record->bank_atas_nama ? ' a.n '.$record->bank_atas_nama : ''))
                            : 'Belum diisi — isi di menu Manajemen > Info Usaha sebelum membuat invoice.'),
                    TextEntry::make('tautan')
                        ->label('Tautan publik')
                        ->columnSpanFull()
                        ->copyable()
                        ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Terkirim, InvoiceStatus::Lunas], true))
                        ->state(fn (Invoice $record): string => app(InvoiceService::class)->urlPublik($record)),
                ]),
            Section::make('Baris Tagihan')
                ->description(fn (Invoice $record): string => $record->draft() ? 'Masih draft — baris bisa diedit lewat tombol "Edit Baris".' : 'Baris terkunci karena invoice sudah tidak draft.')
                ->schema([
                    \Filament\Infolists\Components\View::make('invoice.admin-ringkasan')
                        ->state(fn (Invoice $record): Invoice => $record->load('items', 'orders')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nomor')->label('No. Invoice')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.nama')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('jatuh_tempo')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => $record->lewatJatuhTempo() ? 'danger' : null)
                    ->description(fn (Invoice $record): ?string => $record->lewatJatuhTempo() ? 'Lewat jatuh tempo' : null),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (InvoiceStatus $state): string => self::warnaStatus($state)),
                Tables\Columns\TextColumn::make('total')->label('Total')->state(fn (Invoice $record): string => self::rp((float) $record->total)),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')->state(fn (Invoice $record): string => $record->batal() ? '—' : self::rp($record->saldo())),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(InvoiceStatus::class)),
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                        ->where('nama', 'like', '%'.$search.'%')->orderBy('nama')->limit(30)->pluck('nama', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->nama),
                Tables\Filters\Filter::make('bulan')
                    ->form([Forms\Components\TextInput::make('bulan')->label('Bulan')->type('month')])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['bulan'] ?? null)) {
                            return $query;
                        }
                        $awal = Carbon::parse($data['bulan'].'-01')->startOfMonth();

                        return $query->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()]);
                    })
                    ->indicateUsing(fn (array $data): ?string => filled($data['bulan'] ?? null) ? 'Bulan: '.$data['bulan'] : null),
                Tables\Filters\Filter::make('lewat_jatuh_tempo')
                    ->label('Lewat jatuh tempo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', InvoiceStatus::Terkirim->value)
                        ->whereDate('jatuh_tempo', '<', now()->toDateString())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),
                Tables\Actions\Action::make('unduhPdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Invoice $record): bool => ! $record->batal())
                    ->url(fn (Invoice $record): string => Url::absolute('invoice.pdf', ['invoice' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('tandaiTerkirim')
                    ->label('Tandai Terkirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->draft())
                    ->requiresConfirmation()
                    ->action(fn (Invoice $record) => self::jalankan(fn () => app(InvoiceService::class)->tandaiTerkirim($record, auth()->user()), 'Invoice ditandai terkirim')),
                Tables\Actions\Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Invoice $record): bool => ! $record->batal())
                    ->requiresConfirmation()
                    ->modalDescription('Invoice dibatalkan tidak lagi berlaku sebagai tagihan; order-nya bisa dibuatkan invoice baru.')
                    ->action(fn (Invoice $record) => self::jalankan(fn () => app(InvoiceService::class)->batalkan($record, auth()->user()), 'Invoice dibatalkan')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function warnaStatus(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Lunas => 'success',
            InvoiceStatus::Terkirim => 'info',
            InvoiceStatus::Batal => 'danger',
            default => 'gray',
        };
    }

    public static function rp(float $nilai): string
    {
        return 'Rp'.number_format($nilai, 0, ',', '.');
    }

    /**
     * Jalankan aksi service dengan notifikasi sukses/gagal seragam.
     */
    public static function jalankan(\Closure $aksi, string $pesanSukses): bool
    {
        try {
            $aksi();
            Notification::make()->success()->title($pesanSukses)->send();

            return true;
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();

            return false;
        }
    }
}
