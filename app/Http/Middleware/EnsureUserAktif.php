<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak sesi user yang dinonaktifkan (B20): logout paksa + redirect ke login.
 * Dipasang di grup mobile /teknisi dan middleware panel /admin.
 */
class EnsureUserAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda dinonaktifkan. Hubungi admin.');
        }

        return $next($request);
    }
}
