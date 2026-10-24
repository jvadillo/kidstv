<?php

namespace App\Mail;

use App\Models\LoginToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $purpose,
        public string $code,
        public ?string $link,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            LoginToken::PURPOSE_LOGIN => __('Tu código de acceso: :code', ['code' => $this->code]),
            LoginToken::PURPOSE_CHANGE_EMAIL => __('Confirma tu nuevo correo: :code', ['code' => $this->code]),
            default => __('Código para confirmar un cambio: :code', ['code' => $this->code]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verification',
            text: 'mail.verification-text',
            with: [
                'intro' => match ($this->purpose) {
                    LoginToken::PURPOSE_LOGIN => __('Usa este código o el botón para entrar en tu videoteca familiar.'),
                    LoginToken::PURPOSE_CHANGE_EMAIL => __('Introduce este código para confirmar tu nueva dirección de correo.'),
                    default => __('Alguien (esperamos que tú) quiere hacer un cambio importante en tu cuenta. Introduce este código para confirmarlo.'),
                },
            ],
        );
    }
}
