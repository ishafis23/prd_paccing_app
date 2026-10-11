<?php

use App\Http\Controllers\PayrollSlipController;
use Illuminate\Support\Facades\Route;

/*
| Slip gaji teknisi (dev-plan/hrd/02) — Admin/HR/Owner, hanya setelah ACC.
| Dipisah dari routes/web.php agar tidak bercampur dgn perubahan lain.
*/
Route::middleware('auth')->group(function () {
    Route::get('/slip-gaji/{run}', [PayrollSlipController::class, 'show'])->name('payroll.slip');
    Route::get('/slip-gaji/{run}/pdf', [PayrollSlipController::class, 'pdf'])->name('payroll.slip.pdf');
});
