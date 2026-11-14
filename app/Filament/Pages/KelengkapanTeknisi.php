<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\KelengkapanHarianService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * "Kelengkapan Teknisi" — dashboard pimpinan untuk crosscheck harian 4 kelengkapan administrasi
 * teknisi sebelum dana ditransfer (revisi 14 Nov 2026 poin 21). Read-only.
 */
class KelengkapanTeknisi extends Page
{
    protected static string $view = 'filament.pages.kelengkapan-teknisi';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Kelengkapan Teknisi';

    protected static ?string $title = 'Kelengkapan Administrasi Teknisi';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $slug = 'kelengkapan-teknisi';

    protected static ?int $navigationSort = 0;

    /** Tanggal terpilih (Y-m-d). */
    #[Url]
    public string $tanggal = '';

    /** Saring: semua | kurang. */
    #[Url]
    public string $tampil = 'semua';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->rapikan();
    }

    public function updatedTanggal(): void
    {
        $this->rapikan();
    }

    public function geser(int $hari): void
    {
        $this->tanggal = CarbonImmutable::parse($this->tanggal)->addDays($hari)->toDateString();
        $this->rapikan();
    }

    private function rapikan(): void
    {
        try {
            $t = CarbonImmutable::createFromFormat('Y-m-d', $this->tanggal);
        } catch (\Throwable) {
            $t = false;
        }

        if (! $t || $t->format('Y-m-d') !== $this->tanggal || $t->isFuture()) {
            $this->tanggal = now()->toDateString();
        }

        if (! in_array($this->tampil, ['semua', 'kurang'], true)) {
            $this->tampil = 'semua';
        }
    }

    protected function getViewData(): array
    {
        $service = app(KelengkapanHarianService::class);
        $baris = $service->untukTanggal(CarbonImmutable::parse($this->tanggal));

        return [
            'ringkasan' => $service->ringkas($baris),
            'baris' => $this->tampil === 'kurang' ? $baris->where('lengkap', false)->values() : $baris,
            'tanggalLabel' => CarbonImmutable::parse($this->tanggal)->translatedFormat('l, d F Y'),
            'adalahHariIni' => $this->tanggal === now()->toDateString(),
        ];
    }
}
