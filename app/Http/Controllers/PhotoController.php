<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    /**
     * Upload photo untuk order (terstruktur per layanan & unit)
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:lokasi,cuci,service',
            'unit_number' => 'required|integer|min:1|max:10',
            'photo_position' => 'required|string',
            'file' => 'required|image|mimes:jpeg,png|max:5120', // 5MB
        ]);

        try {
            // Check authorization
            if (!$this->canManageOrder($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke order ini',
                ], 403);
            }

            // Check Game 2 time limit jika applicable
            if ($this->isGame2Photo($validated)) {
                if (!$this->isGame2Allowed()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Game 2 deadline sudah lewat (batas 8:30)',
                    ], 422);
                }
            }

            // Store file
            $file = $request->file('file');
            $filename = $this->generatePhotoFilename($validated);
            $path = $file->storeAs('order-photos', $filename, 'public');

            // Create database record
            $photo = OrderPhoto::create([
                'order_id' => $order->id,
                'type' => $validated['type'],
                'unit_number' => $validated['unit_number'],
                'photo_position' => $validated['photo_position'],
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diunggah',
                'data' => $photo,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah foto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all photos untuk order, organized by type
     */
    public function getByOrder(Order $order): JsonResponse
    {
        if (!$this->canViewOrder($order)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ], 403);
        }

        $photos = $order->photos()->get();

        // Organize photos by type & unit
        $organized = [
            'lokasi' => [],
            'cuci' => [],
            'service' => [],
            'summary' => [
                'total_photos_required' => $this->getTotalPhotosRequired($order),
                'total_photos_uploaded' => $photos->count(),
                'progress_percent' => 0,
                'sections_skipped' => 0,
            ],
        ];

        foreach ($photos as $photo) {
            $organized[$photo->type][] = $photo;
        }

        $total = $organized['summary']['total_photos_required'];
        if ($total > 0) {
            $organized['summary']['progress_percent'] = round(
                ($photos->count() / $total) * 100
            );
        }

        return response()->json([
            'success' => true,
            'data' => $organized,
        ]);
    }

    /**
     * Delete photo
     */
    public function destroy(OrderPhoto $photo): JsonResponse
    {
        $order = $photo->order;

        if (!$this->canManageOrder($order)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ], 403);
        }

        try {
            // Delete file
            \Storage::disk('public')->delete($photo->file_path);

            // Delete record
            $photo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto',
            ], 500);
        }
    }

    // Private helper methods

    private function canManageOrder(Order $order): bool
    {
        $user = auth()->user();
        return $user->isAdmin() || $order->diassignkanKe($user);
    }

    private function canViewOrder(Order $order): bool
    {
        $user = auth()->user();
        return $user->isAdmin() || $order->customer_id === $user->id || $order->diassignkanKe($user);
    }

    private function isGame2Photo(array $validated): bool
    {
        // Game 2 adalah special photo yang di-upload pada hari order
        // Sesuai spec, ini bisa ditentukan berdasarkan context
        // Untuk now, return false (bisa di-refine nanti)
        return false;
    }

    private function isGame2Allowed(): bool
    {
        // Check if current time is before 08:30
        $deadline = \Carbon\Carbon::now()->setTimeFromTimeString('08:30:00');
        return \Carbon\Carbon::now()->lessThan($deadline);
    }

    private function generatePhotoFilename(array $validated): string
    {
        $timestamp = now()->format('YmdHis');
        $type = $validated['type'];
        $unit = $validated['unit_number'] ?? 1;
        $position = $validated['photo_position'];

        return "{$timestamp}_{$type}_{$unit}_{$position}.jpg";
    }

    private function getTotalPhotosRequired(Order $order): int
    {
        // Lokasi: 1
        // Cuci: 5 per unit (bisa multiple)
        // Service: 3 per unit (bisa multiple)
        // Untuk now, return estimate untuk 1 unit cuci + 1 unit service
        return 1 + 5 + 3; // 9 (dapat disesuaikan based on order item)
    }
}
