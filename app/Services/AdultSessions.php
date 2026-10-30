<?php

namespace App\Services;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Sesiones persistentes de adulto. Cada navegador recibe una cookie larga con un
 * token aleatorio (guardado como hash) que permite volver sin pedir otro correo,
 * y que el adulto puede revocar desde su cuenta.
 */
class AdultSessions
{
    public const SESSION_KEY = 'auth_session_id';

    public const CONFIRMED_KEY = 'email_confirmed_at';

    public function start(Request $request, User $user): AuthSession
    {
        $plain = Str::random(64);

        $session = $user->authSessions()->create([
            'token_hash' => hash('sha256', $plain),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(config('nokoplay.auth_session_days')),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $session->id);
        // Acabar de demostrar el control del correo cuenta como confirmación reciente.
        $request->session()->put(self::CONFIRMED_KEY, now()->timestamp);

        $this->queueCookie($plain);

        return $session;
    }

    /**
     * Valida la sesión actual contra su registro persistente o la recupera desde la
     * cookie. Se ejecuta en cada petición web.
     */
    public function resume(Request $request): void
    {
        $guard = Auth::guard('web');

        if ($guard->check()) {
            $session = AuthSession::find($request->session()->get(self::SESSION_KEY));

            if (! $session || $session->user_id !== $guard->id() || $session->isExpired() || $guard->user()->isBlocked()) {
                $this->terminate($request);

                return;
            }

            $this->touch($request, $session);

            return;
        }

        $plain = $request->cookie(config('nokoplay.auth_cookie'));
        if (! is_string($plain) || $plain === '') {
            return;
        }

        $session = AuthSession::with('user')->where('token_hash', hash('sha256', $plain))->first();

        if (! $session || $session->isExpired() || ! $session->user || $session->user->isBlocked()) {
            $session?->delete();
            Cookie::queue(Cookie::forget(config('nokoplay.auth_cookie')));

            return;
        }

        Auth::login($session->user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $session->id);
        $this->touch($request, $session, force: true, plain: $plain);
    }

    public function terminate(Request $request): void
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if ($id) {
            AuthSession::whereKey($id)->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(config('nokoplay.auth_cookie')));
    }

    public function revokeOthers(Request $request, User $user): int
    {
        return $user->authSessions()
            ->whereKeyNot($request->session()->get(self::SESSION_KEY))
            ->delete();
    }

    public function currentId(Request $request): ?int
    {
        return $request->session()->get(self::SESSION_KEY);
    }

    public static function recentlyConfirmed(Request $request): bool
    {
        $at = (int) $request->session()->get(self::CONFIRMED_KEY, 0);

        return $at > now()->subMinutes(config('nokoplay.sensitive_confirmation_minutes'))->timestamp;
    }

    private function touch(Request $request, AuthSession $session, bool $force = false, ?string $plain = null): void
    {
        if (! $force && $session->last_used_at && $session->last_used_at->gt(now()->subMinutes(10))) {
            return;
        }

        $session->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addDays(config('nokoplay.auth_session_days')),
        ])->save();

        // Renovación deslizante de la cookie (solo cuando conocemos el valor en claro).
        $plain ??= $request->cookie(config('nokoplay.auth_cookie'));
        if (is_string($plain) && $plain !== '') {
            $this->queueCookie($plain);
        }
    }

    private function queueCookie(string $plain): void
    {
        Cookie::queue(cookie(
            name: config('nokoplay.auth_cookie'),
            value: $plain,
            minutes: config('nokoplay.auth_session_days') * 24 * 60,
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
