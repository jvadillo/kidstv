<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdultSessions;
use App\Services\Onboarding;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login');
        }

        return view('auth.register', ['email' => $pending['email']]);
    }

    public function store(Request $request, AdultSessions $sessions, Onboarding $onboarding)
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login')->withErrors(['email' => __('La verificación ha caducado. Vuelve a escribir tu correo.')]);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Para crear la cuenta tienes que aceptar las condiciones y la política de privacidad.'),
        ]);

        $user = $onboarding->createAccount($pending['email'], $data['name'] ?? null);

        $request->session()->forget('pending_registration');
        $sessions->start($request, $user);

        // Si ya había empezado el asistente sin cuenta, se guarda lo que preparó.
        if (filled($onboarding->draft($request)['name'] ?? null)) {
            return $onboarding->finish($request, $user);
        }

        return redirect()->route('onboarding.profile');
    }

    /** Correo verificado hace menos de 30 minutos y pendiente de crear cuenta. */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('pending_registration');

        if (! is_array($pending) || ($pending['verified_at'] ?? 0) < now()->subMinutes(30)->timestamp) {
            return null;
        }

        return $pending;
    }
}
