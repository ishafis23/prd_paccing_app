<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('buatInvoice')
                ->label('Buat Invoice')
                ->icon('heroicon-o-document-plus')
                ->visible(fn (Order $record): bool => InvoiceService::boleh(auth()->user())
                    && app(InvoiceService::class)->bisaDibuatInvoice($record))
                ->modalHeading('Buat Invoice')
                ->modalDescription('Baris invoice diturunkan dari layanan order ini; masih bisa diedit selama draft.')
                ->form(OrderResource::formInvoice())
                ->action(fn (Order $record, array $data) => OrderResource::buatInvoice($record, $data)),

            Actions\Action::make('lihatInvoice')
                ->label('Lihat Invoice')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('gray')
                ->visible(fn (Order $record): bool => InvoiceService::boleh(auth()->user())
                    && app(InvoiceService::class)->punyaInvoiceAktif($record))
                ->url(fn (Order $record): string => InvoiceResource::getUrl('view', ['record' => app(InvoiceService::class)->invoiceAktifOrder($record)])),
        ];
    }
}
