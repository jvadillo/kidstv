<?php

if (! function_exists('describe_user_agent')) {
    /** Resume un User-Agent en algo legible ("Chrome en Android", "Televisor LG webOS"...). */
    function describe_user_agent(?string $ua): string
    {
        if (! $ua) {
            return __('Dispositivo desconocido');
        }

        $platform = match (true) {
            str_contains($ua, 'Web0S') || str_contains($ua, 'webOS') => 'LG webOS',
            str_contains($ua, 'Tizen') => 'Samsung Tizen',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        if ($browser && $platform) {
            return __(':browser en :platform', ['browser' => $browser, 'platform' => $platform]);
        }

        return $browser ?? $platform ?? __('Dispositivo desconocido');
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i >= 2 ? 1 : 0, ',', '.').' '.$units[$i];
    }
}

if (! function_exists('youtube_add_url')) {
    /** Punto de entrada para añadir vídeos de YouTube: el buscador si está configurado; si no, pegar enlace. */
    function youtube_add_url(array $parameters = []): string
    {
        return route(\App\Services\YouTubeSearch::enabled() ? 'contents.youtube.search' : 'contents.youtube.create', $parameters);
    }
}
