<?php

namespace App\Filament\Widgets;

use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Services\FinanceService;
use App\Services\OmsetService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanFinanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    // Tidak lazy: angka harus ikut ter-render di respons halaman (dan bisa diuji lewat HTTP).
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin', 'finance']) ?? false;
    }

    protected function getStats(): array
    {
        [$dari, $sampai, $label] = app(OmsetService::class)->rentangDariFilter($this->filters);
        $labaRugi = app(FinanceService::class)->labaRugi($dari, $sampai);

        return [
            Stat::make('Pendapatan', 'Rp'.number_format($labaRugi['pendapatan'], 0, ',', '.'))
                ->description($label)
                ->color('success'),
            Stat::make('Pengeluaran', 'Rp'.number_format($labaRugi['pengeluaran'], 0, ',', '.'))
                ->description($label)
                ->color('danger'),
            Stat::make('Laba/Rugi', 'Rp'.number_format($labaRugi['laba_rugi'], 0, ',', '.'))
                ->description($label)
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
