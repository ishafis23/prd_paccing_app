<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerImportService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('unduhTemplateCustomer')
                ->label('Unduh Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create', Customer::class) ?? false)
                ->action(fn () => app(CustomerImportService::class)->unduhTemplate()),
            Action::make('importCustomer')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', Customer::class) ?? false)
                ->modalHeading('Import Customer dari Excel')
                ->modalSubmitActionLabel('Import Sekarang')
                ->modalWidth('xl')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel / CSV')
                        ->disk('local')
                        ->directory('import-customer')
                        ->maxSize(5120)
                        ->required()
                        ->helperText('.xlsx / .csv — maks. 5 MB & '.CustomerImportService::MAX_BARIS.' baris data. Unduh Template dulu untuk format kolom yang benar.'),
                ])
                ->action(function (array $data): void {
                    $service = app(CustomerImportService::class);

                    $hasil = $service->import(
                        Storage::disk('local')->path($data['file']),
                        auth()->user(),
                    );

                    Storage::disk('local')->delete($data['file']);

                    Notification::make()
                        ->title(sprintf(
                            'Import selesai: %d dibuat, %d dilewati, %d gagal',
                            $hasil['berhasil'],
                            $hasil['dilewati'],
                            $hasil['gagal']
                        ))
                        ->body($hasil['berhasil'] > 0
                            ? 'Customer baru sudah masuk ke daftar.'
                            : 'Tidak ada customer baru yang dibuat.')
                        ->{$hasil['gagal'] > 0 ? 'warning' : 'success'}()
                        ->send();

                    if ($hasil['rincian'] !== []) {
                        $rincian = array_slice($hasil['rincian'], 0, 15);
                        $sisa = count($hasil['rincian']) - count($rincian);

                        if ($sisa > 0) {
                            $rincian[] = "... dan {$sisa} baris lainnya.";
                        }

                        Notification::make()
                            ->title('Rincian baris dilewati/gagal')
                            ->body(implode("\n", $rincian))
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
