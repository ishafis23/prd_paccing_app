<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\UlasanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResiController extends Controller
{
    /**
     * Halaman resi publik (B14a) — read-only, tanpa login, diakses lewat
     * token acak. Detail & styling final di resources/views/resi.blade.php.
     */
    public function show(Order $order, string $token): View
    {
        $this->pastikanSah($order, $token);

        return view('resi', [
            'order' => $order,
            'ulasan' => $order->review,
            'bisaMengulas' => app(UlasanService::class)->bisaDiulas($order),
        ]);
    }

    /** Customer memberi ulasan (bintang + komentar) lewat tautan resi — revisi 14 Nov 2026 poin 8. */
    public function ulasan(Request $request, Order $order, string $token, UlasanService $service): RedirectResponse
    {
        $this->pastikanSah($order, $token);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'komentar' => ['nullable', 'string', 'max:1000'],
        ], ['rating.required' => 'Pilih jumlah bintang terlebih dahulu.']);

        try {
            $service->simpan($order, (int) $data['rating'], $data['komentar'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->with('ulasan_error', $e->getMessage());
        }

        return back()->with('ulasan_status', 'Terima kasih atas penilaian Anda!');
    }

    private function pastikanSah(Order $order, string $token): void
    {
        abort_unless(
            $order->resi_token !== null && hash_equals((string) $order->resi_token, (string) $token),
            404
        );
    }
}
