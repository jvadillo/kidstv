<?php

return [

    /*
    | Correo y acceso sin contraseña
    */
    'login_token_ttl_minutes' => (int) env('NOKOPLAY_LOGIN_TOKEN_TTL', 15),
    'login_code_max_attempts' => 5,
    // Validación DNS de dominios de correo (desactivable en entornos sin salida a Internet).
    'email_dns_check' => (bool) env('NOKOPLAY_EMAIL_DNS_CHECK', true),

    // Sesión persistente del adulto en un navegador (renovación deslizante).
    'auth_session_days' => (int) env('NOKOPLAY_AUTH_SESSION_DAYS', 60),
    'auth_cookie' => 'nokoplay_device',

    // Ventana durante la que se consideran confirmados los cambios sensibles tras revalidar el correo.
    'sensitive_confirmation_minutes' => 10,

    /*
    | Televisores
    */
    'pairing_ttl_minutes' => 10,
    'tv_media_url_ttl_minutes' => 240,
    'pin_max_failures_before_lock' => 3,
    'pin_lock_base_seconds' => 30,
    'pin_lock_max_seconds' => 900,
    'adult_mode_idle_seconds' => 120,

    /*
    | Biblioteca y almacenamiento
    */
    'max_video_mb' => (int) env('NOKOPLAY_MAX_VIDEO_MB', 500),
    'max_audio_mb' => (int) env('NOKOPLAY_MAX_AUDIO_MB', 60),
    'max_cover_mb' => 5,
    'family_quota_mb' => (int) env('NOKOPLAY_FAMILY_QUOTA_MB', 3072),
    // Espacio libre mínimo que debe quedar en el disco del servidor tras aceptar una subida.
    'min_free_disk_mb' => (int) env('NOKOPLAY_MIN_FREE_DISK_MB', 1536),
    'upload_chunk_mb' => 8,

    'video_extensions' => ['mp4', 'm4v'],
    'audio_extensions' => ['mp3', 'm4a'],
    'video_mimes' => ['video/mp4', 'video/x-m4v', 'application/mp4', 'video/quicktime'],
    'audio_mimes' => ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/octet-stream'],

    'ffmpeg' => env('NOKOPLAY_FFMPEG', '/usr/bin/ffmpeg'),
    'ffprobe' => env('NOKOPLAY_FFPROBE', '/usr/bin/ffprobe'),

    // En producción nginx sirve los ficheros privados (con soporte de Range) mediante X-Accel-Redirect.
    'accel_redirect' => (bool) env('NOKOPLAY_ACCEL_REDIRECT', false),
    'accel_prefix' => '/_protected/',

    'avatars' => ['estrella', 'corazon', 'sol', 'luna', 'nube', 'flor', 'cohete', 'pez'],

    // Listas con las que empieza cada familia nueva.
    'default_lists' => ['Dibujos', 'Música', 'Cuentos', 'Aprender'],

    /*
    | Aplicación de televisión
    */
    'webos_ipk' => 'downloads/nokoplay-webos.ipk',
];
