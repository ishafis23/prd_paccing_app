<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TemporaryPhotoUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemporaryPhotoUploadController extends Controller
{
    /**
     * Upload foto ke temporary storage (real-time saat file dipilih)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
            'order_id' => ['required', 'exists:orders,id'],
            'field_name' => ['required', 'string'], // contoh: fotoSebelum, fotoKategori.1.slot1
        ]);

        $order = Order::findOrFail($request->order_id);
        abort_if(! $order->diassignkanKe(auth()->user()), 403);

        // Upload file ke temporary folder
        $file = $request->file('file');
        $filePath = $file->store('temporary-photos', 'public');

        // Simpan record di database
        $tempPhoto = TemporaryPhotoUpload::create([
            'user_id' => auth()->id(),
            'order_id' => $order->id,
            'field_name' => $request->field_name,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diupload',
            'data' => [
                'id' => $tempPhoto->id,
                'file_path' => $filePath,
                'file_name' => $tempPhoto->file_name,
            ],
        ], 201);
    }

    /**
     * Ambil semua temporary photos untuk order tertentu
     * (untuk restore saat page load)
     */
    public function index(Order $order): JsonResponse
    {
        abort_if(! $order->diassignkanKe(auth()->user()), 403);

        $photos = TemporaryPhotoUpload::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->get()
            ->mapWithKeys(fn ($photo) => [$photo->field_name => [
                'id' => $photo->id,
                'file_path' => $photo->file_path,
                'file_name' => $photo->file_name,
            ]]);

        return response()->json([
            'success' => true,
            'data' => $photos,
        ]);
    }

    /**
     * Hapus temporary photo (jika user klik remove)
     */
    public function destroy(TemporaryPhotoUpload $tempPhoto): JsonResponse
    {
        abort_if(! $tempPhoto->user_id === auth()->id(), 403);

        $tempPhoto->deleteFile();
        $tempPhoto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Foto dihapus',
        ]);
    }

    /**
     * Clean up temporary photos untuk order tertentu
     * (dipanggil saat submitLaporan berhasil)
     */
    public function cleanup(Order $order): JsonResponse
    {
        abort_if(! $order->diassignkanKe(auth()->user()), 403);

        TemporaryPhotoUpload::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->each(fn ($photo) => $photo->deleteFile());

        TemporaryPhotoUpload::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Temporary photos dibersihkan',
        ]);
    }
}
