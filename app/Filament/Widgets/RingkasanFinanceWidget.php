<?php

namespace App\Filament\Widgets;

use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Services\FinanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanFinanceWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin', 'finance']) ?? false;
    }

    protected function getStats(): array
    {
        $labaRugi = app(FinanceService::class)->labaRugi();

        return [
            Stat::make('Pendapatan Bulan Ini', 'Rp'.number_format($labaRugi['pendapatan'], 0, ',', '.'))
                ->description('Jasa Rp'.number_format($labaRugi['pendapatan_jasa'], 0, ',', '.').' + Material Rp'.number_format($labaRugi['pendapatan_material'], 0, ',', '.'))
                ->color('success'),
            Stat::make('Pengeluaran Bulan Ini', 'Rp'.number_format($labaRugi['pengeluaran'], 0, ',', '.'))
                ->color('danger'),
            Stat::make('Laba/Rugi Bulan Ini', 'Rp'.number_format($labaRugi['laba_rugi'], 0, ',', '.'))
                ->color($labaRugi['laba_rugi'] >= 0 ? 'success' : 'danger'),
            Stat::make('Notice Servis Jatuh Tempo (H-7)', (string) ServiceReminder::jatuhTempo()->count())
                ->description('Customer perlu dihubungi untuk servis berikutnya')
                ->color('warning'),
            Stat::make('Barang Stok Menipis', (string) StockItem::menipis()->count())
                ->description('Segera restock')
                ->color('danger'),
        ];
    }
}
