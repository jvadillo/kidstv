<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginToken;
use App\Models\User;
use App\Services\AdultSessions;
use App\Services\EmailVerification;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class LoginController extends Controller
{
    public function __construct(
        private EmailVerification $verification,
        private AdultSessions $sessions,
        private Onboarding $onboarding,
    ) {}

    public function show()
    {
        return view('auth.login', ['formToken' => Crypt::encryptString((string) now()->timestamp)]);
    }

    /**
     * Envía el código. Desde el asistente de primeros pasos (onboarding=1) las condiciones
     * se aceptan aquí mismo, y al verificar el correo se guarda directamente lo preparado.
     */
    public function send(Request $request)
    {
        $fromOnboarding = $request->boolean('onboarding');

        $data = $request->validate([
            'email' => ['required', 'string', 'max:254', config('kidstv.email_dns_check') ? 'email:rfc,dns' : 'email:rfc'],
            'terms' => $fromOnboarding ? ['accepted'] : [],
        ], [
            'email.required' => __('Escribe tu correo electrónico.'),
            'email.email' => __('Esta dirección de correo no parece válida o su dominio no existe. Revísala.'),
            'terms.accepted' => __('Para guardar la tele tienes que aceptar las condiciones y la política de privacidad.'),
        ]);

        $email = EmailVerification::normalize($data['email']);
        $request->session()->put('login_email', $email);
        $request->session()->put(Onboarding::CLAIM_KEY, $fromOnboarding);
        $next = redirect()->route($fromOnboarding ? 'onboarding.code' : 'login.code');

        // Protección básica contra registros automatizados: campo trampa y tiempo mínimo
        // de cumplimentación. A un robot se le responde igual que a una persona.
        if (! $this->looksHuman($request)) {
            return $next;
        }

        $this->verification->send($email, LoginToken::PURPOSE_LOGIN, User::where('email', $email)->first(), $request->ip());

        return $next;
    }

    public function showCode(Request $request)
    {
        $email = $request->session()->get('login_email');
        if (! $email) {
            return redirect()->route('login');
        }

        return view('auth.code', ['email' => $email]);
    }

    public function verifyCode(Request $request)
    {
        $email = $request->session()->get('login_email');
        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => __('Vuelve a escribir tu correo para recibir un código.')]);
        }

        $request->validate(['code' => ['required', 'string', 'max:12']], [
            'code.required' => __('Escribe el código de 6 cifras que te hemos enviado.'),
        ]);

        $token = $this->verification->verifyCode($email, LoginToken::PURPOSE_LOGIN, $request->input('code'));

        return $this->complete($request, $token->email);
    }

    public function resend(Request $request)
    {
        $email = $request->session()->get('login_email');
        if (! $email) {
            return redirect()->route('login');
        }

        $key = 'resend:'.$email;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['code' => __('Espera :seconds segundos antes de pedir otro código.', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        RateLimiter::hit($key, 60);

        $this->verification->send($email, LoginToken::PURPOSE_LOGIN, User::where('email', $email)->first(), $request->ip());

        return back()->with('status', __('Te hemos enviado un código nuevo. El anterior ya no sirve.'));
    }

    /**
     * El enlace del correo muestra primero una página de confirmación: así los
     * escáneres antivirus que abren enlaces automáticamente no lo consumen.
     */
    public function showLink(string $token)
    {
        $loginToken = $this->verification->findLink($token);

        if (! $loginToken) {
            return response()->view('auth.link-invalid', [], 410);
        }

        return view('auth.link', ['token' => $token, 'email' => $loginToken->email]);
    }

    public function consumeLink(Request $request, string $token)
    {
        $loginToken = $this->verification->findLink($token);

        if (! $loginToken || ! $this->verification->consumeLink($loginToken)) {
            return response()->view('auth.link-invalid', [], 410);
        }

        return $this->complete($request, $loginToken->email);
    }

    public function logout(Request $request)
    {
        $this->sessions->terminate($request);

        return redirect()->route('home')->with('status', __('Has cerrado la sesión en este navegador.'));
    }

    private function complete(Request $request, string $email)
    {
        $request->session()->forget('login_email');
        $user = User::where('email', $email)->first();

        if ($user?->isBlocked()) {
            return redirect()->route('login')->withErrors(['email' => __('Esta cuenta está bloqueada. Si crees que es un error, escribe al soporte de KidsTV.')]);
        }

        // Desde el asistente ya se aceptaron las condiciones: se crea la cuenta (si no la
        // había) y se guarda en ella el perfil y las listas preparados.
        if ($request->session()->pull(Onboarding::CLAIM_KEY)) {
            $user ??= $this->onboarding->createAccount($email);
            $this->sessions->start($request, $user);

            return $this->onboarding->finish($request, $user);
        }

        if ($user) {
            $this->sessions->start($request, $user);

            return redirect()->intended(route('panel'));
        }

        // Correo verificado pero sin cuenta: falta aceptar las condiciones para crearla.
        $request->session()->put('pending_registration', ['email' => $email, 'verified_at' => now()->timestamp]);

        return redirect()->route('register');
    }

    private function looksHuman(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return false;
        }

        try {
            $renderedAt = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (Throwable) {
            return false;
        }

        return now()->timestamp - $renderedAt >= 2;
    }
}
