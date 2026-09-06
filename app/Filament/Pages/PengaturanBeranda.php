<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\BerandaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Menu Website → Pengaturan Beranda (B38e): peta embed, jam operasional,
 * sosmed, dan toggle seksi landing.
 */
class PengaturanBeranda extends Page
{
    protected static string $view = 'filament.pages.pengaturan-beranda';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Pengaturan Beranda';

    protected static ?string $title = 'Pengaturan Beranda';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'pengaturan-beranda';

    public string $mapsEmbed = '';

    public string $jamOperasional = '';

    public string $sosmedInstagram = '';

    public string $sosmedFacebook = '';

    public bool $tampilLayanan = true;

    public bool $tampilCaraKerja = true;

    public bool $tampilArea = true;

    public bool $tampilPeta = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $s = app(BerandaService::class)->settings();

        $this->mapsEmbed = (string) ($s->maps_embed ?? '');
        $this->jamOperasional = (string) ($s->jam_operasional ?? '');
        $this->sosmedInstagram = (string) ($s->sosmed_instagram ?? '');
        $this->sosmedFacebook = (string) ($s->sosmed_facebook ?? '');
        $this->tampilLayanan = (bool) $s->tampil_layanan;
        $this->tampilCaraKerja = (bool) $s->tampil_cara_kerja;
        $this->tampilArea = (bool) $s->tampil_area;
        $this->tampilPeta = (bool) $s->tampil_peta;
    }

    public function simpan(): void
    {
        $this->validate([
            'mapsEmbed' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && (! str_starts_with($value, 'https://') || ! str_contains($value, 'google.com/maps'))) {
                    $fail('Tempel URL embed Google Maps (src iframe google.com/maps/embed...) — lihat petunjuk di halaman.');
                }
            }],
            'jamOperasional' => ['nullable', 'string', 'max:255'],
            'sosmedInstagram' => ['nullable', 'string', 'max:255'],
            'sosmedFacebook' => ['nullable', 'string', 'max:255'],
        ]);

        app(BerandaService::class)->simpanSettings([
            'maps_embed' => $this->mapsEmbed,
            'jam_operasional' => $this->jamOperasional,
            'sosmed_instagram' => $this->sosmedInstagram,
            'sosmed_facebook' => $this->sosmedFacebook,
            'tampil_layanan' => $this->tampilLayanan,
            'tampil_cara_kerja' => $this->tampilCaraKerja,
            'tampil_area' => $this->tampilArea,
            'tampil_peta' => $this->tampilPeta,
        ]);

        Notification::make()
            ->title('Pengaturan beranda disimpan')
            ->success()
            ->send();
    }
}
