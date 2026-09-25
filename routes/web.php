<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ResiController;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\TeknisExpenseController;
use App\Livewire\Auth\Login;
use App\Livewire\Portal\Dashboard as PortalDashboard;
use App\Livewire\Portal\Login as PortalLogin;
use App\Livewire\Teknisi\AbsensiScan;
use App\Livewire\Teknisi\Akun;
use App\Livewire\Teknisi\CapaianKerja;
use App\Livewire\Teknisi\JadwalHariIni;
use App\Livewire\Teknisi\LaporanPengeluaran;
use App\Livewire\Teknisi\OrderDetail;
use App\Livewire\Teknisi\RiwayatAbsensi;
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
    // dev-plan/15: {kode?} opsional — dari scan QR (absen datang) atau
    // langsung dari menu tanpa kode (absen pulang / lihat status hari ini).
    Route::get('/absensi/{kode?}', AbsensiScan::class)->name('teknisi.absensi');
    Route::get('/riwayat-absensi', RiwayatAbsensi::class)->name('teknisi.riwayat-absensi');
    Route::get('/riwayat', RiwayatPengerjaan::class)->name('teknisi.riwayat');
    Route::get('/capaian', CapaianKerja::class)->name('teknisi.capaian');
    Route::get('/laporan-pengeluaran', LaporanPengeluaran::class)->name('teknisi.laporan-pengeluaran');
    Route::get('/akun', Akun::class)->name('teknisi.akun');

    // Photo endpoints (Phase 03)
    Route::post('/order/{order}/photo', [PhotoController::class, 'store'])->name('teknisi.photo.store');
    Route::get('/order/{order}/photos', [PhotoController::class, 'getByOrder'])->name('teknisi.photo.index');
    Route::delete('/photo/{photo}', [PhotoController::class, 'destroy'])->name('teknisi.photo.destroy');

    // Expense endpoints (Phase 03)
    Route::post('/expense', [TeknisExpenseController::class, 'store'])->name('teknisi.expense.store');
    Route::get('/expenses', [TeknisExpenseController::class, 'index'])->name('teknisi.expense.index');
    Route::get('/expense/{teknisExpense}', [TeknisExpenseController::class, 'show'])->name('teknisi.expense.show');
    Route::put('/expense/{teknisExpense}', [TeknisExpenseController::class, 'update'])->name('teknisi.expense.update');
    Route::delete('/expense/{teknisExpense}', [TeknisExpenseController::class, 'destroy'])->name('teknisi.expense.destroy');
    Route::get('/expense-summary/daily', [TeknisExpenseController::class, 'dailySummary'])->name('teknisi.expense.daily-summary');
    Route::get('/expense-summary/monthly', [TeknisExpenseController::class, 'monthlySummary'])->name('teknisi.expense.monthly-summary');
});

// Resi publik (B14a) — read-only tanpa login; token acak di orders.resi_token.
Route::get('/resi/{order}/{token}', [ResiController::class, 'show'])->name('resi.show');

// Surat Jalan publik (dev-plan/12 §3.12) — read-only tanpa login, khusus korporat.
Route::get('/surat-jalan/{order}/{token}', [SuratJalanController::class, 'show'])->name('surat-jalan.show');

// Portal Customer (dev-plan/12 §3.6) — guard 'customer' terpisah dari
// admin/teknisi, 1 akun login per customer (keputusan 13 Sept).
Route::get('/portal/login', PortalLogin::class)->name('portal.login');

Route::post('/portal/logout', function () {
    Auth::guard('customer')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('portal.login');
})->middleware('auth:customer')->name('portal.logout');

Route::middleware('auth:customer')->prefix('portal')->group(function () {
    Route::get('/', PortalDashboard::class)->name('portal.dashboard');
});

// Admin approval endpoints (Phase 03)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('/expense/{teknisExpense}/approve', [TeknisExpenseController::class, 'approve'])->name('admin.expense.approve');
    Route::post('/expense/{teknisExpense}/reject', [TeknisExpenseController::class, 'reject'])->name('admin.expense.reject');
    Route::get('/expenses', [TeknisExpenseController::class, 'index'])->name('admin.expense.index');
    Route::get('/expense-summary/daily', [TeknisExpenseController::class, 'dailySummary'])->name('admin.expense.daily-summary');
    Route::get('/expense-summary/monthly', [TeknisExpenseController::class, 'monthlySummary'])->name('admin.expense.monthly-summary');
});
