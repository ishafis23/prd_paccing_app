<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Auth\Access\AuthorizationException;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        // Status invoice mengikuti pembayaran: dihitung ulang tiap daftar dibuka.
        app(InvoiceService::class)->segarkanSemuaAktif();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('buatMultiOrder')
                ->label('Buat Invoice (Multi-Order)')
                ->icon('heroicon-o-document-plus')
                ->modalHeading('Buat Invoice dari beberapa order')
                ->modalDescription('Pilih customer lalu centang order selesai miliknya — semuanya masuk ke SATU invoice.')
                ->form([
                    Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                            ->where('nama', 'like', '%'.$search.'%')->orderBy('nama')->limit(30)->pluck('nama', 'id')->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->nama)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('order_ids', []))
                        ->required(),
                    Forms\Components\CheckboxList::make('order_ids')
                        ->label('Order selesai (belum punya invoice aktif)')
                        ->options(fn (Forms\Get $get): array => filled($get('customer_id'))
                            ? app(InvoiceService::class)->orderTersedia((int) $get('customer_id'))
                                ->mapWithKeys(fn (Order $o): array => [$o->id => self::labelOrder($o)])->all()
                            : [])
                        ->required()
                        ->columns(1),
                    Forms\Components\DatePicker::make('tanggal')->default(now())->required(),
                    Forms\Components\DatePicker::make('jatuh_tempo')->label('Jatuh tempo')->default(now()->addDays(7))->required(),
                    Forms\Components\Textarea::make('catatan')->label('Catatan (opsional)'),
                ])
                ->action(function (array $data) {
                    try {
                        $orders = Order::query()->whereIn('id', $data['order_ids'])->get();
                        $invoice = app(InvoiceService::class)->buat($orders, auth()->user(), $data);
                    } catch (BusinessRuleException|AuthorizationException $e) {
                        Notification::make()->danger()->title('Gagal membuat invoice')->body($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Invoice '.$invoice->nomor.' dibuat (draft)')->send();
                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                }),
        ];
    }

    private static function labelOrder(Order $o): string
    {
        return collect([
            '#'.$o->id,
            $o->tanggal_jadwal?->format('d M Y'),
            $o->customerAddress?->nama_lokasi,
            $o->ringkasanLayanan(),
            InvoiceResource::rp($o->total()),
        ])->filter()->implode(' · ');
    }
}
