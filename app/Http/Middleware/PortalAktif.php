<?php

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal Corporate Customer (dev-plan/22) — hanya customer jenis `company`
 * yang aktif. Dicek di SETIAP request, bukan hanya saat login, supaya akun
 * yang dinonaktifkan / berhenti berlangganan langsung tertolak di tengah sesi.
 */
class PortalAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer
            && (! $customer->isCompany() || $customer->status === CustomerStatus::Nonaktif)) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')
                ->with('portal_error', 'Akses Portal hanya untuk customer perusahaan yang aktif. Hubungi admin.');
        }

        return $next($request);
    }
}
