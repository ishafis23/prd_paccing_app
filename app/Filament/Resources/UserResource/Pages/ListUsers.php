<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\UserImportService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('unduhTemplatePengguna')
                ->label('Unduh Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false)
                ->action(fn () => app(UserImportService::class)->unduhTemplate()),
            Action::make('importPengguna')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false)
                ->modalHeading('Import Pengguna dari Excel')
                ->modalSubmitActionLabel('Import Sekarang')
                ->modalWidth('xl')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel / CSV')
                        ->disk('local')
                        ->directory('import-pengguna')
                        ->maxSize(2048)
                        ->required()
                        ->helperText('.xlsx / .csv — maks. 2 MB & 200 baris data. Unduh Template dulu untuk format kolom yang benar.'),
                    TextInput::make('password_default')
                        ->label('Password default (bila kolom password kosong)')
                        ->password()
                        ->revealable()
                        ->default(UserImportService::DEFAULT_PASSWORD)
                        ->helperText('Min. 8 karakter. Dipakai hanya untuk baris yang kolom password-nya dikosongkan.'),
                ])
                ->action(function (array $data): void {
                    $service = app(UserImportService::class);

                    $hasil = $service->import(
                        Storage::disk('local')->path($data['file']),
                        auth()->user(),
                        $data['password_default'] ?? null,
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
                            ? 'Akun baru sudah bisa dipakai.'
                            : 'Tidak ada akun baru yang dibuat.')
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
