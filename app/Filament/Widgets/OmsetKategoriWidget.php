<?php

namespace App\Filament\Widgets;

use App\Services\OmsetService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Satu kartu per kategori pekerjaan (Cuci AC · Service AC · Pasang/Pengadaan,
 * + kategori lain bila ada transaksi): unit, omset total, jasa, material.
 * Tidak digabung. Ikut filter periode dashboard.
 */
class OmsetKategoriWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin', 'finance']) ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $omset = app(OmsetService::class);
        [$dari, $sampai, $label] = $omset->rentangDariFilter($this->filters);
        $rp = fn (float $v): string => 'Rp'.number_format($v, 0, ',', '.');

        return collect($omset->kartu($dari, $sampai))
            ->map(fn (array $k): Stat => Stat::make($k['label'].' — '.$label, $rp($k['total']))
                ->description(number_format($k['unit'], 0, ',', '.').' unit · Jasa '.$rp($k['jasa']).' · Material '.$rp($k['material']))
                ->color($k['total'] > 0 ? 'success' : 'gray'))
            ->all();
    }
}
