<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Halaman "Orderan Harian" (§3.3): daftar semua order pada satu tanggal
 * lintas teknisi, utk kebutuhan operasional harian admin — beda dari
 * "Jadwal Saya" milik teknisi yg cuma menampilkan order dirinya sendiri.
 */
class OrderanHarian extends Page
{
    protected static string $view = 'filament.pages.orderan-harian';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Orderan Harian';

    protected static ?string $title = 'Orderan Harian';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'orderan-harian';

    protected static ?int $navigationSort = -1;

    #[Url]
    public string $tanggal = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value, RoleName::Hr->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->tanggal === '') {
            $this->tanggal = now()->toDateString();
        }
    }

    public function hariIni(): void
    {
        $this->tanggal = now()->toDateString();
    }

    protected function getViewData(): array
    {
        $orders = Order::query()
            ->whereDate('tanggal_jadwal', $this->tanggal)
            ->with(['customer', 'serviceCatalog', 'teknisi', 'timTeknisi', 'workReports', 'latestPayment', 'orderItems', 'pelaporPerbaikan'])
            ->orderByRaw('jam_jadwal IS NULL, jam_jadwal')
            ->get();

        return [
            'orders' => $orders,
            'ringkasan' => $this->ringkasanStatus($orders),
            'tanggal' => $this->tanggal,
            'isHariIni' => $this->tanggal === now()->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<string, int>
     */
    private function ringkasanStatus(Collection $orders): array
    {
        $urutan = array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases());

        return $orders->countBy(fn (Order $o) => $o->status->value)
            ->sortBy(fn ($jumlah, $status) => array_search($status, $urutan, true))
            ->all();
    }
}
