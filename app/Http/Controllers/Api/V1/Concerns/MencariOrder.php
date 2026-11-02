<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\CashHandoverService;
use Illuminate\Http\Request;

trait MencariOrder
{
    /**
     * Order milik tim teknisi login (PIC atau anggota). Bukan miliknya → 404
     * (tidak membocorkan keberadaan). `$tulis` = aksi yang mengubah data: ditolak
     * selama ada setoran tunai telat (sama dengan gerbang akses order di web).
     */
    protected function cariOrder(Request $request, int|string $id, bool $tulis = false): Order
    {
        $order = Order::query()->untukTeknisi($request->user()->id)->findOrFail($id);

        if ($tulis) {
            $telat = app(CashHandoverService::class)->telat($request->user());
            if ($telat->isNotEmpty()) {
                $t = $telat->first();
                throw new BusinessRuleException(sprintf(
                    'Akses order ditahan. Laporkan setoran order #%d sebesar Rp %s terlebih dahulu.',
                    $t->id,
                    number_format((float) $t->total(), 0, ',', '.'),
                ));
            }
        }

        return $order;
    }
}
