<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KidsTV</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2133;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f1e7;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;padding:32px;">
                <tr>
                    <td>
                        <p style="margin:0 0 4px;font-size:20px;font-weight:700;color:#2b2560;">KidsTV</p>
                        <p style="margin:0 0 24px;font-size:14px;color:#5d5f73;">{{ __('Videoteca familiar') }}</p>
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">{{ $intro }}</p>
                        <p style="margin:0 0 8px;font-size:14px;color:#5d5f73;">{{ __('Tu código') }}</p>
                        <p style="margin:0 0 24px;font-size:36px;font-weight:700;letter-spacing:8px;color:#2b2560;">{{ $code }}</p>
                        @if ($link)
                            <p style="margin:0 0 24px;">
                                <a href="{{ $link }}" style="display:inline-block;background:#2b2560;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:12px;">{{ __('Entrar en mi videoteca') }}</a>
                            </p>
                        @endif
                        <p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#5d5f73;">{{ __('Caduca en :minutes minutos y solo se puede usar una vez.', ['minutes' => $minutes]) }}</p>
                        <p style="margin:0;font-size:14px;line-height:1.5;color:#5d5f73;">{{ __('Si no lo has pedido tú, ignora este correo: nadie podrá entrar sin este código.') }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
