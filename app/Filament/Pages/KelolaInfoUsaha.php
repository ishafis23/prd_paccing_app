<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Services\BusinessInfoService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

/**
 * Menu Manajemen → "Info Usaha" (B37): edit nama usaha, alamat, kontak WA,
 * email, nama pemilik, serta upload/hapus logo. Owner & Admin.
 */
class KelolaInfoUsaha extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.pages.kelola-info-usaha';

    protected static ?string $navigationGroup = 'Manajemen';

    protected static ?string $navigationLabel = 'Info Usaha';

    protected static ?string $title = 'Info Usaha';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $slug = 'info-usaha';

    public string $namaUsaha = '';

    public string $alamat = '';

    public string $kontakWa = '';

    public string $email = '';

    public string $namaPemilik = '';

    public string $bankNama = '';

    public string $bankRekening = '';

    public string $bankAtasNama = '';

    public bool $hapusLogo = false;

    public $logoFile;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $info = app(BusinessInfoService::class)->data();

        $this->namaUsaha = (string) $info->nama_usaha;
        $this->alamat = (string) ($info->alamat ?? '');
        $this->kontakWa = (string) ($info->kontak_wa ?? '');
        $this->email = (string) ($info->email ?? '');
        $this->namaPemilik = (string) ($info->nama_pemilik ?? '');
        $this->bankNama = (string) ($info->bank_nama ?? '');
        $this->bankRekening = (string) ($info->bank_rekening ?? '');
        $this->bankAtasNama = (string) ($info->bank_atas_nama ?? '');
    }

    public function simpan(): void
    {
        $this->validate([
            'namaUsaha' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'kontakWa' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'namaPemilik' => ['nullable', 'string', 'max:255'],
            'bankNama' => ['nullable', 'string', 'max:100'],
            'bankRekening' => ['nullable', 'string', 'max:50'],
            'bankAtasNama' => ['nullable', 'string', 'max:255'],
            'logoFile' => ['nullable', 'image', 'max:2048'],
        ]);

        try {
            app(BusinessInfoService::class)->perbarui([
                'nama_usaha' => $this->namaUsaha,
                'alamat' => $this->alamat,
                'kontak_wa' => $this->kontakWa,
                'email' => $this->email,
                'nama_pemilik' => $this->namaPemilik,
                'bank_nama' => $this->bankNama,
                'bank_rekening' => $this->bankRekening,
                'bank_atas_nama' => $this->bankAtasNama,
            ], auth()->user(), $this->logoFile, $this->hapusLogo);

            $this->logoFile = null;
            $this->hapusLogo = false;

            Notification::make()
                ->title('Info usaha disimpan')
                ->body('Nama, logo, dan kontak kini otomatis dipakai di seluruh tampilan.')
                ->success()
                ->send();
        } catch (BusinessRuleException $e) {
            Notification::make()
                ->danger()
                ->title('Gagal menyimpan')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function urlFile(string $path): string
    {
        return asset('storage/'.ltrim($path, '/'));
    }

    protected function getViewData(): array
    {
        $info = app(BusinessInfoService::class)->data();

        return [
            'info' => $info,
            'logoUrl' => app(BusinessInfoService::class)->logoUrl(),
            'pengubahNama' => $info->relationLoaded('pengubah') ? optional($info->pengubah)->name : $info->pengubah?->name,
            'pengubahWaktu' => $info->updated_at?->format('d M Y H:i'),
        ];
    }
}
