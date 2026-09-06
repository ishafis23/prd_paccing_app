<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class ResiController extends Controller
{
    /**
     * Halaman resi publik (B14a) — read-only, tanpa login, diakses lewat
     * token acak. Detail & styling final di resources/views/resi.blade.php.
     */
    public function show(Order $order, string $token): View
    {
        $sah = $order->resi_token !== null
            && hash_equals((string) $order->resi_token, (string) $token);

        abort_unless($sah, 404);

        return view('resi', ['order' => $order]);
    }
}
