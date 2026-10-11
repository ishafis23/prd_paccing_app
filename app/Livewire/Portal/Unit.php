<?php

namespace App\Livewire\Portal;

use App\Enums\OrderStatus;
use App\Models\CustomerAcUnit;
use App\Services\PortalAksesService;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman "Unit" Portal Corporate (dev-plan/22 §4.2) — rekap unit AC per
 * cabang: terakhir dikerjakan + jadwal berikutnya.
 */
class Unit extends Component
{
    #[Url]
    public string $cabang = '';

    public function render()
    {
        $akses = app(PortalAksesService::class);
        $customer = $akses->customer();

        $units = CustomerAcUnit::query()
            ->where('customer_id', $customer->id)
            ->with(['customerAddress', 'latestOrderItem.order', 'orderItems.order'])
            ->when($this->cabang !== '', fn ($q) => $q->where('customer_address_id', (int) $this->cabang))
            ->orderBy('customer_address_id')
            ->orderBy('kode_unit')
            ->get();

        $statusTerjadwal = [
            OrderStatus::Terjadwal->value,
            OrderStatus::MenujuLokasi->value,
            OrderStatus::Dikerjakan->value,
        ];

        $rekap = $units->map(function (CustomerAcUnit $unit) use ($statusTerjadwal): array {
            $terakhir = $unit->latestOrderItem;
            $terakhir = ($terakhir !== null && $terakhir->order !== null && $terakhir->order->status !== OrderStatus::Batal)
                ? $terakhir
                : null;

            $terjadwal = $unit->orderItems
                ->first(fn ($item) => $item->order !== null
                    && in_array($item->order->status->value, $statusTerjadwal, true));

            $status = match (true) {
                $terjadwal !== null => 'Terjadwal',
                $terakhir !== null && $terakhir->order->status === OrderStatus::Selesai => 'Sudah',
                default => 'Belum',
            };

            return [
                'unit' => $unit,
                'terakhir' => $terakhir,
                'terjadwal' => $terjadwal,
                'status' => $status,
            ];
        });

        return view('livewire.portal.unit', [
            'customer' => $customer,
            'rekap' => $rekap,
            'alamatOptions' => $customer->addresses()->orderBy('id')->get(),
        ])->layout('layouts.portal', ['title' => 'Unit AC']);
    }
}
