KidsTV · {{ __('Videoteca familiar') }}

{{ $intro }}

{{ __('Tu código') }}: {{ $code }}
@if ($link)

{{ __('O entra con este enlace') }}: {{ $link }}
@endif

{{ __('Caduca en :minutes minutos y solo se puede usar una vez.', ['minutes' => $minutes]) }}
{{ __('Si no lo has pedido tú, ignora este correo: nadie podrá entrar sin este código.') }}
