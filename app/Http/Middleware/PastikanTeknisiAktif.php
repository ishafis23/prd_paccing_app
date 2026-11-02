<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API teknisi: hanya user ber-role teknisi & berstatus aktif; selain itu token dicabut. */
class PastikanTeknisiAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status !== UserStatus::Aktif) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi admin.', 'code' => 'akun_nonaktif'], 401);
        }

        if (! $user->hasRole(RoleName::Teknisi->value)) {
            return response()->json(['message' => 'Akun ini bukan akun teknisi.', 'code' => 'bukan_teknisi'], 403);
        }

        return $next($request);
    }
}
