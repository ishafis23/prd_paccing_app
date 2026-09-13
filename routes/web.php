<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\ResiController;
use App\Http\Controllers\SuratJalanController;
use App\Livewire\Auth\Login;
use App\Livewire\Teknisi\Akun;
use App\Livewire\Teknisi\CapaianKerja;
use App\Livewire\Teknisi\JadwalHariIni;
use App\Livewire\Teknisi\OrderDetail;
use App\Livewire\Teknisi\RiwayatPengerjaan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:teknisi', 'user.aktif'])->prefix('teknisi')->group(function () {
    Route::get('/', JadwalHariIni::class)->name('teknisi.jadwal');
    Route::get('/order/{order}', OrderDetail::class)->name('teknisi.order');
    Route::get('/riwayat', RiwayatPengerjaan::class)->name('teknisi.riwayat');
    Route::get('/capaian', CapaianKerja::class)->name('teknisi.capaian');
    Route::get('/akun', Akun::class)->name('teknisi.akun');
});

// Resi publik (B14a) — read-only tanpa login; token acak di orders.resi_token.
Route::get('/resi/{order}/{token}', [ResiController::class, 'show'])->name('resi.show');

// Surat Jalan publik (dev-plan/12 §3.12) — read-only tanpa login, khusus korporat.
Route::get('/surat-jalan/{order}/{token}', [SuratJalanController::class, 'show'])->name('surat-jalan.show');
