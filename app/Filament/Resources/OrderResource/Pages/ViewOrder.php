<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('kirimLaporanKeCustomer')
                ->label('Kirim Laporan ke Customer')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (Order $record): bool => OrderResource::bolehKirimLaporan($record))
                ->requiresConfirmation()
                ->modalHeading('Kirim Laporan Pengerjaan')
                ->modalDescription('PDF laporan pengerjaan akan dikirim ke WhatsApp customer.')
                ->action(function (Order $record): void {
                    OrderResource::kirimLaporanPengerjaan($record, auth()->user());
                    $record->refresh();
                }),

            Actions\Action::make('tandaiLaporanTerkirim')
                ->label('Tandai Laporan Terkirim')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->laporan_terkirim_pada === null)
                ->requiresConfirmation()
                ->modalHeading('Tandai laporan terkirim')
                ->modalDescription('Gunakan bila laporan dikirim di luar aplikasi. Tindakan ini tidak mengirim WhatsApp.')
                ->action(function (Order $record): void {
                    OrderResource::tandaiLaporanTerkirim($record, auth()->user());
                    Notification::make()->success()->title('Laporan ditandai terkirim')->send();
                    $record->refresh();
                }),

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
