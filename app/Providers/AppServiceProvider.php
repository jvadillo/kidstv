<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $tooMany = fn (string $message) => fn (Request $request, array $headers) => $request->expectsJson()
            ? response()->json(['error' => 'too_many_requests', 'message' => $message], 429, $headers)
            : back()->withInput()->withErrors(['email' => $message, 'code' => $message, 'pin' => $message]);

        RateLimiter::for('login-request', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip())
                ->response($tooMany(__('Demasiadas solicitudes seguidas. Espera un minuto y vuelve a intentarlo.'))),
            Limit::perHour(30)->by('ip:'.$request->ip())
                ->response($tooMany(__('Has hecho demasiadas solicitudes. Inténtalo de nuevo dentro de un rato.'))),
            Limit::perHour(6)->by('email:'.Str::lower((string) $request->input('email', $request->session()->get('login_email'))))
                ->response($tooMany(__('Ya hemos enviado varios correos a esta dirección. Revisa tu bandeja (y la carpeta de spam) o espera un rato.'))),
        ]);

        RateLimiter::for('login-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip())
            ->response($tooMany(__('Demasiados intentos. Espera un minuto.'))));

        RateLimiter::for('confirm-send', fn (Request $request) => Limit::perHour(6)->by('user:'.$request->user()?->id)
            ->response($tooMany(__('Ya te hemos enviado varios códigos. Revisa tu correo o espera un rato.'))));

        RateLimiter::for('pair-claim', fn (Request $request) => Limit::perMinute(10)->by('user:'.$request->user()?->id)
            ->response($tooMany(__('Demasiados intentos de vinculación. Espera un minuto.'))));

        RateLimiter::for('tv-pairing', fn (Request $request) => Limit::perHour(30)->by($request->ip()));

        RateLimiter::for('tv-poll', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('tv-api', fn (Request $request) => Limit::perMinute(300)->by(
            $request->bearerToken() ? 'device:'.hash('sha256', $request->bearerToken()) : 'ip:'.$request->ip()
        ));

        RateLimiter::for('tv-pin', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
