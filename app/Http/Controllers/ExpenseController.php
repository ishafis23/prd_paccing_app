<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function update(Request $request, $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return response()->json(['message' => 'Pengeluaran tidak ditemukan'], 404);
        }

        if (! $this->bolehKelola()) {
            return response()->json(['message' => 'Tidak memiliki akses'], 403);
        }

        $validated = $request->validate([
            'kategori' => 'required|in:material,perawatan',
            'qty' => 'required|integer|min:1',
            'harga' => 'required|numeric|min:0',
            'nominal' => 'required|numeric|min:1',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $expense->update($validated);

        return response()->json(['message' => 'Pengeluaran diperbarui', 'data' => $expense]);
    }

    public function destroy($id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return response()->json(['message' => 'Pengeluaran tidak ditemukan'], 404);
        }

        if (! $this->bolehKelola()) {
            return response()->json(['message' => 'Tidak memiliki akses'], 403);
        }

        $expense->delete();

        return response()->json(['message' => 'Pengeluaran dihapus']);
    }

    private function bolehKelola(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Admin->value,
            RoleName::Finance->value,
        ]) ?? false;
    }
}
