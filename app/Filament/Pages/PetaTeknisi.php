<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Halaman "Peta Teknisi": posisi GPS terakhir teknisi yang sedang
 * menuju_lokasi/dikerjakan, dikirim berkala dari HP teknisi (lihat
 * TeknisiService::updateLokasi & OrderDetail Livewire).
 */
class PetaTeknisi extends Page
{
    protected static string $view = 'filament.pages.peta-teknisi';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Peta Teknisi';

    protected static ?string $title = 'Peta Teknisi';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $slug = 'peta-teknisi';

    /** Sinyal GPS dianggap basi setelah sekian menit tanpa update. */
    public const STALE_MENIT = 5;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Admin->value, RoleName::Finance->value, RoleName::Hr->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getViewData(): array
    {
        $teknisi = $this->teknisiSedangBertugas();

        $markers = $teknisi
            ->filter(fn (array $t) => $t['lat'] !== null && $t['lng'] !== null)
            ->map(fn (array $t) => [
                ...$t,
                'updated_label' => $t['updated_at']?->diffForHumans() ?? '-',
                'order_status' => $t['order_status']->value,
            ])
            ->values();

        return [
            'teknisi' => $teknisi,
            'markers' => $markers,
            'markersKey' => md5($markers->map(fn (array $t) => "{$t['teknisi_id']}:{$t['lat']},{$t['lng']}:{$t['stale']}")->implode('|')),
            'center' => $markers->isNotEmpty()
                ? [$markers->avg('lat'), $markers->avg('lng')]
                : [-2.5, 118.0], // fallback: tengah Indonesia bila belum ada titik.
            'zoom' => $markers->isNotEmpty() ? 12 : 5,
            'staleMenit' => self::STALE_MENIT,
        ];
    }

    /**
     * Satu baris per teknisi yang sedang jadi anggota tim order aktif
     * (menuju_lokasi/dikerjakan), lengkap dengan posisi GPS terakhirnya
     * (bisa null kalau belum pernah mengirim / izin GPS ditolak).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function teknisiSedangBertugas(): Collection
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::MenujuLokasi, OrderStatus::Dikerjakan])
            ->with(['customer', 'teknisi', 'timTeknisi'])
            ->get();

        return $orders
            ->flatMap(function (Order $order) {
                // PIC (teknisi_id) belum tentu punya baris order_technicians
                // (order lama/legacy) — gabung keduanya, sama seperti
                // Order::diassignkanKe().
                $anggota = $order->timTeknisi->when(
                    $order->teknisi !== null && ! $order->timTeknisi->contains('id', $order->teknisi->id),
                    fn ($tim) => $tim->push($order->teknisi),
                );

                return $anggota->map(function (User $t) use ($order) {
                    $updatedAt = $t->last_location_at;

                    return [
                        'teknisi_id' => $t->id,
                        'nama' => $t->name,
                        'lat' => $t->last_latitude !== null ? (float) $t->last_latitude : null,
                        'lng' => $t->last_longitude !== null ? (float) $t->last_longitude : null,
                        'updated_at' => $updatedAt,
                        'stale' => $updatedAt !== null && $updatedAt->lt(now()->subMinutes(self::STALE_MENIT)),
                        'order_id' => $order->id,
                        'order_status' => $order->status,
                        'customer' => $order->customer?->nama ?? '-',
                        'alamat' => $order->alamat_pengerjaan,
                    ];
                });
            })
            ->unique('teknisi_id')
            ->sortBy('nama')
            ->values();
    }
}
