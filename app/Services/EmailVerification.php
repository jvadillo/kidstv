<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Envío y comprobación de enlaces/códigos de un solo uso por correo.
 * Es el único mecanismo de identificación del adulto (no hay contraseñas).
 */
class EmailVerification
{
    public function send(string $email, string $purpose, ?User $user = null, ?string $ip = null): LoginToken
    {
        $email = self::normalize($email);

        // Solo puede haber un código vivo por correo y propósito: pedir otro invalida el anterior.
        LoginToken::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $link = Str::random(48);
        $code = (string) random_int(100000, 999999);

        $token = LoginToken::create([
            'purpose' => $purpose,
            'email' => $email,
            'user_id' => $user?->id,
            'link_hash' => hash('sha256', $link),
            'code_hash' => Hash::make($code),
            'ip_address' => $ip,
            'expires_at' => now()->addMinutes(config('nokoplay.login_token_ttl_minutes')),
        ]);

        Mail::to($email)->send(new VerificationCodeMail(
            purpose: $purpose,
            code: $code,
            link: $purpose === LoginToken::PURPOSE_LOGIN ? route('login.link', $link) : null,
            minutes: config('nokoplay.login_token_ttl_minutes'),
        ));

        return $token;
    }

    /**
     * Comprueba el código de 6 cifras del último envío. Lanza errores de validación
     * con mensajes claros (incorrecto, caducado, demasiados intentos).
     */
    public function verifyCode(string $email, string $purpose, string $code, string $field = 'code'): LoginToken
    {
        $token = LoginToken::where('email', self::normalize($email))
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        $max = config('nokoplay.login_code_max_attempts');

        if (! $token || $token->used_at !== null) {
            throw ValidationException::withMessages([$field => __('Este código ya no es válido. Solicita uno nuevo.')]);
        }
        if ($token->expires_at->isPast()) {
            throw ValidationException::withMessages([$field => __('El código ha caducado. Solicita uno nuevo.')]);
        }
        if ($token->attempts >= $max) {
            throw ValidationException::withMessages([$field => __('Has superado el número de intentos. Solicita un código nuevo.')]);
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $token->code_hash)) {
            $token->increment('attempts');
            $left = $max - $token->attempts;

            throw ValidationException::withMessages([$field => $left > 0
                ? trans_choice('El código no es correcto. Te queda :count intento.|El código no es correcto. Te quedan :count intentos.', $left)
                : __('Has superado el número de intentos. Solicita un código nuevo.')]);
        }

        $token->update(['used_at' => now()]);

        return $token;
    }

    /** Busca un enlace de acceso todavía válido (sin consumirlo). */
    public function findLink(string $plain): ?LoginToken
    {
        $token = LoginToken::where('link_hash', hash('sha256', $plain))
            ->where('purpose', LoginToken::PURPOSE_LOGIN)
            ->first();

        return $token && $token->isUsable() ? $token : null;
    }

    /** Consume el enlace de forma atómica: dos peticiones simultáneas no pueden usarlo a la vez. */
    public function consumeLink(LoginToken $token): bool
    {
        return LoginToken::whereKey($token->id)->whereNull('used_at')->update(['used_at' => now()]) === 1;
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
