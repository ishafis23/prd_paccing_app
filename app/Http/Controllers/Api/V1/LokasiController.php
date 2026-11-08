<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LacakLokasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pelacakan posisi teknisi selama jam kerja (dev-plan/32). */
class LokasiController extends Controller
{
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titik' => ['required', 'array', 'min:1', 'max:'.LacakLokasiService::MAKS_TITIK],
            'titik.*.client_uuid' => ['required', 'uuid'],
            'titik.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'titik.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'titik.*.akurasi' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'titik.*.direkam_pada' => ['required', 'date'],
        ]);

        return response()->json(['data' => app(LacakLokasiService::class)->simpanBatch($request->user(), $data['titik'])]);
    }
}
