<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class SuratJalanController extends Controller
{
    /**
     * Halaman Surat Jalan publik (dev-plan/12 §3.12) — read-only, tanpa
     * login, diakses lewat token acak. Pola sama dgn ResiController (B14a).
     */
    public function show(Order $order, string $token): View
    {
        $sah = $order->surat_jalan_token !== null
            && hash_equals((string) $order->surat_jalan_token, (string) $token);

        abort_unless($sah, 404);

        $order->load(['customer', 'orderItems.acUnit', 'timTeknisi', 'teknisi']);

        return view('surat-jalan', ['order' => $order]);
    }
}
