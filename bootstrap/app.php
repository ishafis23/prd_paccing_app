<?php

use App\Http\Middleware\EnsureUserAktif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'user.aktif' => EnsureUserAktif::class,
        ]);

        // Portal Customer (dev-plan/12 §3.6) pakai guard 'customer' terpisah
        // dari admin/teknisi — tamu yg lewat /portal/* diarahkan ke login
        // Portal-nya sendiri, bukan /login admin/teknisi.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('portal*') ? route('portal.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
