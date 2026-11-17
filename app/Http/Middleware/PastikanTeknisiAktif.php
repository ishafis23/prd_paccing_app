<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Support\Facades\DB;
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

        // Penanda "sedang membuka app" (waktu server). Ditulis paling sering tiap 30 detik.
        // Gagal menulis (mis. migrasi belum dijalankan) tidak boleh menjatuhkan API teknisi.
        try {
            if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subSeconds(30))) {
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
                $user->last_seen_at = now();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
