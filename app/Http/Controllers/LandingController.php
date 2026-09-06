<?php

namespace App\Http\Controllers;

use App\Services\BerandaService;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $beranda = app(BerandaService::class);

        return view('landing', [
            'slides' => $beranda->slidesAktif(),
            'layanan' => $beranda->layananBeranda(),
            'settings' => $beranda->settings(),
            'areaLayanan' => ['Makassar', 'Gowa', 'Maros'],
        ]);
    }
}
