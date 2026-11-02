<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak app mobile versi lama (426 Upgrade Required). Header X-App-Version
 * dikirim app; tanpa header (mis. curl/tes) dibiarkan lewat.
 */
class CekVersiApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $versi = $request->header('X-App-Version');
        $minimum = (string) config('api.min_app_version', '1.0.0');

        if ($versi !== null && version_compare((string) $versi, $minimum, '<')) {
            return response()->json([
                'message' => "Versi aplikasi ({$versi}) sudah terlalu lama. Perbarui ke versi {$minimum} atau lebih baru.",
                'code' => 'upgrade_required',
                'min_app_version' => $minimum,
            ], 426);
        }

        return $next($request);
    }
}
