<?php

namespace App\Http\Controllers;

use App\Models\TeknisExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

class TeknisExpenseController extends Controller
{
    /**
     * Create new teknisi expense
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_input' => 'required|date|before_or_equal:today',
            'kategori' => 'required|in:bensin,makan,material,transport,lainnya',
            'nominal' => 'required|integer|min:1000|max:5000000',
            'keterangan' => 'nullable|string|max:255',
            'bukti_file' => 'nullable|image|mimes:jpeg,png|max:5120',
        ]);

        try {
            $validated['teknisi_id'] = auth()->id();
            $validated['status'] = 'pending';

            // Store bukti file if provided
            if ($request->hasFile('bukti_file')) {
                $file = $request->file('bukti_file');
                $path = $file->storeAs('expense-bukti', uniqid() . '_' . $file->getClientOriginalName(), 'public');
                $validated['bukti_file'] = $path;
            }

            $expense = TeknisExpense::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil dicatat',
                'data' => $expense,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pengeluaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of expenses (teknisi: own only, admin: all)
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeknisExpense::query();

        // Filter by teknisi if user is not admin
        if (!auth()->user()->isAdmin()) {
            $query->where('teknisi_id', auth()->id());
        }

        // Filter by month if provided
        if ($request->has('month')) {
            $query->byMonth($request->month);
        }

        // Filter by kategori
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by teknisi (admin only)
        if ($request->has('teknisi_id') && auth()->user()->isAdmin()) {
            $query->where('teknisi_id', $request->teknisi_id);
        }

        $expenses = $query->orderBy('tanggal_input', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'last_page' => $expenses->lastPage(),
            ],
        ]);
    }

    /**
     * Get expense detail
     */
    public function show(TeknisExpense $expense): JsonResponse
    {
        if (!$this->canView($expense)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $expense->load('teknisi', 'approvedBy'),
        ]);
    }

    /**
     * Update expense (teknisi only, if still pending)
     */
    public function update(Request $request, TeknisExpense $expense): JsonResponse
    {
        if (!$this->canEdit($expense)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa mengubah pengeluaran ini',
            ], 403);
        }

        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengeluaran pending yang bisa diubah',
            ], 422);
        }

        $validated = $request->validate([
            'nominal' => 'nullable|integer|min:1000|max:5000000',
            'keterangan' => 'nullable|string|max:255',
        ]);

        try {
            $expense->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil diubah',
                'data' => $expense,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah pengeluaran',
            ], 500);
        }
    }

    /**
     * Delete expense (teknisi only, if still pending)
     */
    public function destroy(TeknisExpense $expense): JsonResponse
    {
        if (!$this->canEdit($expense)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa menghapus pengeluaran ini',
            ], 403);
        }

        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengeluaran pending yang bisa dihapus',
            ], 422);
        }

        try {
            $expense->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengeluaran',
            ], 500);
        }
    }

    /**
     * Approve expense (admin only)
     */
    public function approve(Request $request, TeknisExpense $expense): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang bisa approve',
            ], 403);
        }

        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengeluaran pending yang bisa di-approve',
            ], 422);
        }

        $validated = $request->validate([
            'nominal' => 'nullable|integer|min:1000|max:5000000',
            'catatan_approval' => 'nullable|string',
        ]);

        try {
            // Update nominal jika admin mengubah
            if ($request->has('nominal')) {
                $expense->nominal = $validated['nominal'];
            }

            $expense->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'catatan_approval' => $validated['catatan_approval'] ?? null,
                'tanggal_approve' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil di-approve',
                'data' => $expense,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve pengeluaran',
            ], 500);
        }
    }

    /**
     * Reject expense (admin only)
     */
    public function reject(Request $request, TeknisExpense $expense): JsonResponse
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang bisa reject',
            ], 403);
        }

        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengeluaran pending yang bisa di-reject',
            ], 422);
        }

        $validated = $request->validate([
            'catatan_approval' => 'required|string|min:5',
        ]);

        try {
            $expense->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'catatan_approval' => $validated['catatan_approval'],
                'tanggal_approve' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil di-reject',
                'data' => $expense,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject pengeluaran',
            ], 500);
        }
    }

    /**
     * Get daily summary
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->query('date', today()->format('Y-m-d'));

        $query = TeknisExpense::byDate($date);

        if (!auth()->user()->isAdmin()) {
            $query->byTeknisi(auth()->id());
        }

        $expenses = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_pending' => $expenses->where('status', 'pending')->sum('nominal'),
                'total_approved' => $expenses->where('status', 'approved')->sum('nominal'),
                'by_kategori' => $this->groupByKategori($expenses),
            ],
        ]);
    }

    /**
     * Get monthly summary
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $month = $request->query('month', today()->format('Y-m'));

        $query = TeknisExpense::byMonth($month);

        if (!auth()->user()->isAdmin()) {
            $query->byTeknisi(auth()->id());
        }

        $expenses = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'total_pending' => $expenses->where('status', 'pending')->sum('nominal'),
                'total_approved' => $expenses->where('status', 'approved')->sum('nominal'),
                'by_kategori' => $this->groupByKategori($expenses),
                'total_items' => $expenses->count(),
            ],
        ]);
    }

    // Private helper methods

    private function canView(TeknisExpense $expense): bool
    {
        return auth()->user()->isAdmin() || $expense->teknisi_id === auth()->id();
    }

    private function canEdit(TeknisExpense $expense): bool
    {
        return !auth()->user()->isAdmin() && $expense->teknisi_id === auth()->id();
    }

    private function groupByKategori($expenses): array
    {
        $kategoris = ['bensin', 'makan', 'material', 'transport', 'lainnya'];
        $result = [];

        foreach ($kategoris as $kat) {
            $filtered = $expenses->where('kategori', $kat);
            $result[$kat] = [
                'pending' => $filtered->where('status', 'pending')->sum('nominal'),
                'approved' => $filtered->where('status', 'approved')->sum('nominal'),
            ];
        }

        return $result;
    }
}
