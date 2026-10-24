<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\RequireRecentConfirmation;
use App\Http\Middleware\ResumeAdultSession;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Controllers\Tv\YouTubeBridgeController;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Sin sesión ni cookies: la incrusta la app de TV como iframe de terceros.
            Route::get('/tv/youtube', YouTubeBridgeController::class)->name('tv.youtube');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Solo se llega a la aplicación a través del proxy local (Caddy → nginx).
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [ResumeAdultSession::class]);
        // Debe ejecutarse antes que «auth» (que Laravel adelanta por prioridad) para que la
        // cookie persistente pueda restaurar la sesión del adulto.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResumeAdultSession::class);
        $middleware->alias([
            'confirmed' => RequireRecentConfirmation::class,
            'device' => AuthenticateDevice::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
