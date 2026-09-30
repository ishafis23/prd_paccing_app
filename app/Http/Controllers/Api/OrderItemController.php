<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function update(Request $request, OrderItem $orderItem)
    {
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:1',
            'harga' => 'required|numeric|min:0',
            'catatan' => 'nullable|string|max:500',
        ]);

        $orderItem->update($validated);

        return response()->json(['message' => 'Item updated successfully']);
    }

    public function destroy(OrderItem $orderItem)
    {
        $orderItem->delete();

        return response()->json(['message' => 'Item deleted successfully']);
    }
}
