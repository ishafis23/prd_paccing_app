<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\BusinessInfoService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\NotifikasiWaService;
use App\Services\WhatsappService;
use App\Support\Url;
use App\Support\WhatsappTemplate;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Status mengikuti pembayaran: dihitung ulang tiap halaman dibuka.
        app(InvoiceService::class)->segarkanStatus($this->getRecord());
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editBaris')
                ->label('Edit Baris')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (Invoice $record): bool => $record->draft())
                ->modalWidth('5xl')
                ->fillForm(fn (Invoice $record): array => [
                    'baris' => $record->items->map(fn (InvoiceItem $i): array => [
                        'order_id' => $i->order_id,
                        'nama' => $i->nama,
                        'deskripsi' => $i->deskripsi,
                        'jumlah' => (float) $i->jumlah,
                        'harga' => (float) $i->harga,
                    ])->all(),
                    'jatuh_tempo' => $record->jatuh_tempo?->toDateString(),
                    'catatan' => $record->catatan,
                ])
                ->form([
                    Forms\Components\Repeater::make('baris')
                        ->label('Baris tagihan')
                        ->columns(6)
                        ->schema([
                            Forms\Components\Hidden::make('order_id'),
                            Forms\Components\TextInput::make('nama')->label('Item')->required()->maxLength(255)->columnSpan(2),
                            Forms\Components\Textarea::make('deskripsi')->label('Deskripsi')->rows(2)->columnSpan(2),
                            Forms\Components\TextInput::make('jumlah')->numeric()->minValue(0.01)->required(),
                            Forms\Components\TextInput::make('harga')->label('Tarif')->numeric()->minValue(0)->prefix('Rp')->required(),
                        ])
                        ->minItems(1)
                        ->addActionLabel('Tambah baris'),
                    Forms\Components\DatePicker::make('jatuh_tempo')->label('Jatuh tempo'),
                    Forms\Components\Textarea::make('catatan')->label('Catatan'),
                ])
                ->action(function (array $data, Invoice $record) {
                    if (InvoiceResource::jalankan(
                        fn () => app(InvoiceService::class)->perbaruiBaris($record, array_values($data['baris']), auth()->user(), $data['catatan'] ?? '', $data['jatuh_tempo'] ?? null),
                        'Baris invoice disimpan',
                    )) {
                        $record->refresh();
                    }
                }),

            Actions\Action::make('tandaiTerkirim')
                ->label('Tandai Terkirim')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (Invoice $record): bool => $record->draft())
                ->requiresConfirmation()
                ->action(function (Invoice $record) {
                    InvoiceResource::jalankan(fn () => app(InvoiceService::class)->tandaiTerkirim($record, auth()->user()), 'Invoice ditandai terkirim');
                    $record->refresh();
                }),

            Actions\Action::make('unduhPdf')
                ->label('Unduh PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (Invoice $record): bool => ! $record->batal())
                ->url(fn (Invoice $record): string => Url::absolute('invoice.pdf', ['invoice' => $record->id]))
                ->openUrlInNewTab(),

            Actions\Action::make('unduhPdfLampiran')
                ->label('Unduh PDF + Lampiran Laporan')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->visible(fn (Invoice $record): bool => ! $record->batal())
                ->url(fn (Invoice $record): string => Url::absolute('invoice.pdf', ['invoice' => $record->id]).'?lampiran=1')
                ->openUrlInNewTab(),

            Actions\Action::make('kirimWa')
                ->label('Kirim via WA')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->modalHeading('Kirim Invoice via WhatsApp')
                ->modalDescription('PDF invoice dibuat & dikirim sebagai dokumen. Nomor tujuan boleh diedit untuk tes atau nomor lain.')
                ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Terkirim, InvoiceStatus::Lunas], true))
                ->form([
                    Forms\Components\TextInput::make('target')
                        ->label('Nomor Tujuan')
                        ->tel()
                        ->required()
                        ->default(fn (Invoice $record): string => (string) preg_replace('/\D/', '', (string) $record->customer?->no_hp))
                        ->helperText('Terisi dari nomor customer — boleh diganti (tes / nomor lain).'),
                    Forms\Components\Textarea::make('pesan')
                        ->label('Caption')
                        ->rows(8)
                        ->required()
                        ->default(fn (Invoice $record): string => WhatsappTemplate::invoice(
                            $record,
                            app(BusinessInfoService::class)->namaUsaha(),
                            app(InvoiceService::class)->urlPublik($record),
                        )),
                ])
                ->action(function (Invoice $record, array $data): void {
                    $user = auth()->user();
                    $device = $user?->waDevice;

                    if (! $device) {
                        Notification::make()
                            ->title('Belum ada device WhatsApp')
                            ->body('Sambungkan device dulu di menu WhatsApp → Perangkat & Tes WA.')
                            ->danger()->send();

                        return;
                    }

                    try {
                        $pdf = app(InvoicePdfService::class)->render($record, false);
                        $namafile = InvoicePdfService::namaFile($record, false);
                        $path = app(WhatsappService::class)->simpanSnapshot($pdf, $namafile);

                        $log = app(WhatsappService::class)->kirimDokumen(
                            $user,
                            $device,
                            (string) $data['target'],
                            $path,
                            (string) $data['pesan'],
                            $namafile,
                            $record,
                            'invoice',
                        );

                        Notification::make()
                            ->title($log->sukses() ? 'Invoice terkirim' : 'Gagal mengirim invoice')
                            ->body($log->sukses() ? 'Ke '.$log->target.' — tercatat di Riwayat Kirim WA.' : (string) $log->error_message)
                            ->status($log->sukses() ? 'success' : 'danger')
                            ->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title('Tidak bisa kirim')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('kirimNotifLunas')
                ->label('Kirim Notif Pembayaran')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalHeading('Kirim Notifikasi Pembayaran + Invoice')
                ->modalDescription('Kirim pesan "pembayaran diterima" beserta PDF invoice ke customer.')
                ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Lunas)
                ->action(function (Invoice $record): void {
                    try {
                        $log = app(NotifikasiWaService::class)->kirimPembayaranLunas($record, auth()->user());

                        Notification::make()
                            ->title($log->sukses() ? 'Notifikasi pembayaran terkirim' : 'Gagal mengirim notifikasi')
                            ->body($log->sukses() ? 'Ke '.$log->target.' — tercatat di Riwayat Kirim WA.' : (string) $log->error_message)
                            ->status($log->sukses() ? 'success' : 'danger')
                            ->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title('Tidak bisa kirim')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('bukaWhatsapp')
                ->label('Buka WhatsApp (manual)')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (Invoice $record): bool => in_array($record->status, [InvoiceStatus::Terkirim, InvoiceStatus::Lunas], true))
                ->url(fn (Invoice $record): string => app(InvoiceService::class)->urlWa($record))
                ->openUrlInNewTab(),

            Actions\Action::make('batalkan')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Invoice $record): bool => ! $record->batal())
                ->requiresConfirmation()
                ->action(function (Invoice $record) {
                    InvoiceResource::jalankan(fn () => app(InvoiceService::class)->batalkan($record, auth()->user()), 'Invoice dibatalkan');
                    $record->refresh();
                }),
        ];
    }
}
