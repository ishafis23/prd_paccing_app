<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Enums\CustomerTipe;
use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Perkembangan customer baru vs lama (revisi 14 Nov 2026 poin 20).
 * "Baru bulan ini" = customer bertanda baru yang dibuat bulan berjalan.
 */
class CustomerTipeStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $baru = Customer::where('tipe_customer', CustomerTipe::Baru->value)->count();
        $lama = Customer::where('tipe_customer', CustomerTipe::Lama->value)->count();

        $baruBulanIni = Customer::where('tipe_customer', CustomerTipe::Baru->value)
            ->where('created_at', '>=', now()->startOfMonth())->count();
        $baruBulanLalu = Customer::where('tipe_customer', CustomerTipe::Baru->value)
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->count();

        return [
            Stat::make('Customer Baru', $baru)->description('Total bertanda baru')->color('success'),
            Stat::make('Customer Lama', $lama)->description('Total bertanda lama')->color('info'),
            Stat::make('Baru Bulan Ini', $baruBulanIni)
                ->description('Bulan lalu: '.$baruBulanLalu)
                ->color($baruBulanIni >= $baruBulanLalu ? 'success' : 'warning'),
        ];
    }
}
