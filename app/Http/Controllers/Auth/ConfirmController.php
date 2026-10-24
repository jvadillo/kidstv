<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginToken;
use App\Services\AdultSessions;
use App\Services\EmailVerification;
use Illuminate\Http\Request;

/** Revalidación de identidad por correo antes de cambios sensibles. */
class ConfirmController extends Controller
{
    public function __construct(private EmailVerification $verification) {}

    public function show(Request $request)
    {
        return view('auth.confirm', [
            'email' => $request->user()->email,
            'sent' => $request->session()->get('confirm_sent', false),
        ]);
    }

    public function send(Request $request)
    {
        $user = $request->user();
        $this->verification->send($user->email, LoginToken::PURPOSE_CONFIRM, $user, $request->ip());
        $request->session()->put('confirm_sent', true);

        return redirect()->route('confirm.show')->with('status', __('Te hemos enviado un código a :email.', ['email' => $user->email]));
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']], [
            'code.required' => __('Escribe el código de 6 cifras que te hemos enviado.'),
        ]);

        $this->verification->verifyCode($request->user()->email, LoginToken::PURPOSE_CONFIRM, $request->input('code'));

        $request->session()->forget('confirm_sent');
        $request->session()->put(AdultSessions::CONFIRMED_KEY, now()->timestamp);

        return redirect()->intended(route('account.show'))->with('status', __('Identidad confirmada. Puedes continuar.'));
    }
}
