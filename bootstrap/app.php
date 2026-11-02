<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\CekVersiApp;
use App\Http\Middleware\EnsureUserAktif;
use App\Http\Middleware\PastikanTeknisiAktif;
use App\Http\Middleware\PortalAktif;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/payroll.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'user.aktif' => EnsureUserAktif::class,
            'portal.aktif' => PortalAktif::class,
            'api.versi' => CekVersiApp::class,
            'api.teknisi' => PastikanTeknisiAktif::class,
        ]);

        // Cek versi app dijalankan paling awal (sebelum autentikasi) untuk semua /api/*.
        $middleware->api(prepend: [CekVersiApp::class]);

        // Portal Customer (dev-plan/12 §3.6) pakai guard 'customer' terpisah
        // dari admin/teknisi — tamu yg lewat /portal/* diarahkan ke login
        // Portal-nya sendiri, bukan /login admin/teknisi.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('portal*') ? route('portal.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API mobile (dev-plan/mobile-app/02 §4): semua error /api/* berbentuk JSON
        // {message, code, errors?} dan aturan bisnis diterjemahkan di SATU tempat ini.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'aturan_bisnis'], 422);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: 'Anda tidak berhak melakukan aksi ini.', 'code' => 'dilarang'], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.', 'code' => 'tidak_terautentikasi'], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->validator->errors()->first() ?: 'Data tidak valid.',
                    'code' => 'validasi',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Data tidak ditemukan.', 'code' => 'tidak_ditemukan'], 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 403) {
                return response()->json(['message' => $e->getMessage() ?: 'Anda tidak berhak melakukan aksi ini.', 'code' => 'dilarang'], 403);
            }
        });
    })->create();
