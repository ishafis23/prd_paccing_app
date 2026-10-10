<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncomeCategory;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function update(Request $request, $id)
    {
        $orderItem = OrderItem::find($id);
        if (! $orderItem) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $validated = $request->validate([
            'jumlah' => 'sometimes|required|numeric|min:1',
            'harga' => 'sometimes|required|numeric|min:0',
            'catatan' => 'nullable|string|max:500',
            'dibatalkan' => 'sometimes|boolean',
            'komponen' => 'sometimes|in:jasa,material',
        ]);

        // Ubah komponen omset (jasa/material) — Owner/Admin/Finance, bukan teknisi.
        if (array_key_exists('komponen', $validated)) {
            try {
                app(OrderService::class)->ubahKomponenItem($orderItem, IncomeCategory::from($validated['komponen']), $request->user());
            } catch (AuthorizationException $e) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            $orderItem->refresh();
        }

        // Tandai/aktifkan kembali unit "tidak jadi/batal" (revisi customer).
        if (array_key_exists('dibatalkan', $validated)) {
            try {
                $service = app(OrderService::class);
                if ($validated['dibatalkan']) {
                    $service->batalkanItem($orderItem, $request->user());
                } else {
                    $service->aktifkanItem($orderItem, $request->user());
                }
            } catch (BusinessRuleException|AuthorizationException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $orderItem->refresh();
        }

        $isi = array_intersect_key($validated, array_flip(['jumlah', 'harga', 'catatan']));
        if ($isi !== []) {
            $orderItem->update($isi);
        }

        return response()->json(['message' => 'Item updated successfully', 'data' => $orderItem]);
    }

    public function destroy($id)
    {
        $orderItem = OrderItem::find($id);
        if (! $orderItem) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $orderItem->delete();

        return response()->json(['message' => 'Item deleted successfully']);
    }
}
