<?php

use App\Http\Controllers\Api\V1\AbsensiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\JadwalController;
use App\Http\Controllers\Api\V1\KeuanganController;
use App\Http\Controllers\Api\V1\LaporanController;
use App\Http\Controllers\Api\V1\LokasiController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PengeluaranController;
use Illuminate\Support\Facades\Route;

/*
| API mobile teknisi — kontrak: dev-plan/32-api-teknisi-v1.md.
| Prefix /api/v1, token Sanctum (Authorization: Bearer ...), header X-App-Version.
| Semua data dibatasi ke teknisi login (order milik tim lain → 404).
*/
Route::prefix('v1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login');

    Route::middleware(['auth:sanctum', 'api.teknisi', 'throttle:api'])->group(function (): void {
        // Sesi, profil, perangkat push
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/akun/foto-profil', [AuthController::class, 'fotoProfil'])->middleware('throttle:api-tulis');
        Route::post('/perangkat', [AuthController::class, 'daftarPerangkat']);
        Route::delete('/perangkat/{token}', [AuthController::class, 'hapusPerangkat']);

        // Administrasi (banner & penahan berangkat)
        Route::get('/administrasi', [KeuanganController::class, 'administrasi']);

        // Absensi, cuci motor, pulang cepat
        Route::get('/absensi/hari-ini', [AbsensiController::class, 'hariIni']);
        Route::post('/absensi/datang', [AbsensiController::class, 'datang'])->middleware('throttle:api-tulis');
        Route::post('/absensi/pulang', [AbsensiController::class, 'pulang'])->middleware('throttle:api-tulis');
        Route::get('/absensi/riwayat', [AbsensiController::class, 'riwayat']);
        Route::get('/cuci-motor/rekan', [AbsensiController::class, 'rekanCuciMotor']);
        Route::post('/cuci-motor', [AbsensiController::class, 'cuciMotor'])->middleware('throttle:api-tulis');
        Route::get('/pulang-cepat', [AbsensiController::class, 'pulangCepat']);
        Route::post('/pulang-cepat', [AbsensiController::class, 'ajukanPulangCepat']);

        // Pelacakan posisi terus-menerus selama jam kerja (batch, idempoten per client_uuid)
        Route::post('/lokasi/batch', [LokasiController::class, 'batch'])->middleware('throttle:api-tulis');

        // Jadwal, riwayat, lengkapi laporan
        Route::get('/jadwal', [JadwalController::class, 'jadwal']);
        Route::get('/riwayat', [JadwalController::class, 'riwayat']);
        Route::get('/lengkapi-laporan', [JadwalController::class, 'lengkapi']);

        // Order & aksi lapangan
        Route::get('/layanan/opsi', [OrderController::class, 'opsiLayanan']);
        Route::get('/material/opsi', [LaporanController::class, 'opsiMaterial']);
        Route::get('/order/{order}', [OrderController::class, 'show'])->whereNumber('order');

        Route::middleware('throttle:api-tulis')->whereNumber(['order', 'item', 'unit', 'foto', 'id'])->group(function (): void {
            Route::post('/order/{order}/berangkat', [OrderController::class, 'berangkat']);
            Route::post('/order/{order}/lokasi', [OrderController::class, 'lokasi']);
            Route::post('/order/{order}/check-in', [OrderController::class, 'checkIn']);
            Route::post('/order/{order}/kendala', [OrderController::class, 'kendala']);
            Route::post('/order/{order}/perbaikan', [OrderController::class, 'perbaikan']);
            Route::post('/order/{order}/tutup', [OrderController::class, 'tutup']);

            Route::post('/order/{order}/layanan', [OrderController::class, 'tambahLayanan']);
            Route::delete('/order/{order}/layanan/{item}', [OrderController::class, 'hapusLayanan']);
            Route::post('/order/{order}/item/{item}/batal', [OrderController::class, 'batalkanItem']);
            Route::delete('/order/{order}/item/{item}/batal', [OrderController::class, 'aktifkanItem']);

            Route::post('/order/{order}/pembayaran/metode', [OrderController::class, 'metode']);
            Route::post('/order/{order}/pembayaran/bukti', [OrderController::class, 'bukti']);
            Route::post('/order/{order}/pembayaran/lapor-bayar', [OrderController::class, 'laporBayar']);

            // Laporan pengerjaan
            Route::put('/order/{order}/unit/{unit}', [LaporanController::class, 'simpanUnit']);
            Route::get('/order/{order}/foto', [LaporanController::class, 'daftarFoto']);
            Route::post('/order/{order}/foto', [LaporanController::class, 'unggahFoto']);
            Route::post('/order/{order}/foto/{foto}', [LaporanController::class, 'gantiFoto']);
            Route::delete('/order/{order}/foto/{foto}', [LaporanController::class, 'hapusFoto']);
            Route::delete('/order/{order}/foto-sementara/{id}', [LaporanController::class, 'hapusFotoSementara']);
            Route::post('/order/{order}/laporan', [LaporanController::class, 'kirim']);
        });

        // Keuangan teknisi
        Route::get('/pengeluaran', [PengeluaranController::class, 'index']);
        Route::get('/pengeluaran/opsi', [PengeluaranController::class, 'opsi']);
        Route::post('/pengeluaran', [PengeluaranController::class, 'store'])->middleware('throttle:api-tulis');
        Route::put('/pengeluaran/{id}', [PengeluaranController::class, 'update'])->whereNumber('id');
        Route::delete('/pengeluaran/{id}', [PengeluaranController::class, 'destroy'])->whereNumber('id');

        Route::get('/pendapatan/ringkasan', [KeuanganController::class, 'pendapatanRingkasan']);
        Route::get('/pendapatan', [KeuanganController::class, 'pendapatan']);
        Route::get('/games/harian', [KeuanganController::class, 'games']);
        Route::get('/capaian', [KeuanganController::class, 'capaian']);
        Route::get('/insentif', [KeuanganController::class, 'insentif']);

        Route::get('/setoran', [KeuanganController::class, 'setoran']);
        Route::post('/setoran', [KeuanganController::class, 'catatSetoran'])->middleware('throttle:api-tulis');

        Route::get('/gaji', [KeuanganController::class, 'gaji']);
        Route::get('/gaji/{id}/slip', [KeuanganController::class, 'slipGaji'])->whereNumber('id');
        Route::get('/kasbon', [KeuanganController::class, 'kasbon']);
    });
});
