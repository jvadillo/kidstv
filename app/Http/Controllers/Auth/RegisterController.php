<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use App\Services\AdultSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request, AdultSessions $sessions)
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

        $user = DB::transaction(function () use ($pending, $data) {
            if ($existing = User::where('email', $pending['email'])->first()) {
                return $existing;
            }

            $family = Family::create(['name' => null]);
            foreach (config('kidstv.default_categories') as $i => $name) {
                $family->categories()->create(['name' => $name, 'position' => $i]);
            }

            return User::create([
                'family_id' => $family->id,
                'email' => $pending['email'],
                'name' => $data['name'] ?? null,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]);
        });

        $request->session()->forget('pending_registration');
        $sessions->start($request, $user);

        return redirect()->route('welcome');
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
