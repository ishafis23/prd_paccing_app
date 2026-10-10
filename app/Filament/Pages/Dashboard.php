<?php

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Dashboard awal dengan filter periode (bulan ⇄ rentang tanggal + preset).
 * State filter ada di URL (`?filters[mode]=…`) dan diteruskan ke semua widget
 * lewat InteractsWithPageFilters; tidak disimpan di session.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Periode')
                ->schema([
                    Radio::make('mode')
                        ->label('Mode')
                        ->options(['bulan' => 'Bulan', 'rentang' => 'Rentang tanggal'])
                        ->default('bulan')
                        ->inline()
                        ->live(),
                    TextInput::make('bulan')
                        ->label('Bulan')
                        ->type('month')
                        ->default(now()->format('Y-m'))
                        ->visible(fn ($get): bool => ($get('mode') ?? 'bulan') === 'bulan')
                        ->live(),
                    DatePicker::make('dari')
                        ->label('Dari')
                        ->visible(fn ($get): bool => $get('mode') === 'rentang')
                        ->live(),
                    DatePicker::make('sampai')
                        ->label('Sampai')
                        ->visible(fn ($get): bool => $get('mode') === 'rentang')
                        ->live(),
                ])
                ->columns(['md' => 4]),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('hariIni')->label('Hari ini')->color('gray')->action(fn () => $this->terapkanPreset('hari_ini')),
            Action::make('pekanIni')->label('Pekan ini')->color('gray')->action(fn () => $this->terapkanPreset('pekan_ini')),
            Action::make('bulanIni')->label('Bulan ini')->color('gray')->action(fn () => $this->terapkanPreset('bulan_ini')),
            Action::make('bulanLalu')->label('Bulan lalu')->color('gray')->action(fn () => $this->terapkanPreset('bulan_lalu')),
        ];
    }

    /**
     * Preset cepat: hari_ini | pekan_ini | bulan_ini | bulan_lalu.
     */
    public function terapkanPreset(string $preset): void
    {
        $sekarang = CarbonImmutable::now();

        $filters = match ($preset) {
            'hari_ini' => ['mode' => 'rentang', 'dari' => $sekarang->toDateString(), 'sampai' => $sekarang->toDateString()],
            'pekan_ini' => ['mode' => 'rentang', 'dari' => $sekarang->startOfWeek()->toDateString(), 'sampai' => $sekarang->endOfWeek()->toDateString()],
            'bulan_lalu' => ['mode' => 'bulan', 'bulan' => $sekarang->subMonthNoOverflow()->format('Y-m')],
            default => ['mode' => 'bulan', 'bulan' => $sekarang->format('Y-m')],
        };

        $this->filters = $filters;
        $this->getFiltersForm()->fill($filters);
    }
}
