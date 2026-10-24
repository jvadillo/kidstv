<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const PANEL_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: https://i.ytimg.com; media-src 'self'; connect-src 'self'; "
        ."frame-src https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; "
        ."form-action 'self'; frame-ancestors 'none'";

    // Página puente del reproductor de YouTube: debe poder incrustarse en la app de TV
    // empaquetada (origen file://), por eso no limita frame-ancestors.
    private const YOUTUBE_BRIDGE_CSP = "default-src 'self'; script-src 'self' https://www.youtube.com https://s.ytimg.com; "
        ."style-src 'self' 'unsafe-inline'; img-src 'self' data: https://i.ytimg.com; "
        ."frame-src https://www.youtube-nocookie.com https://www.youtube.com; connect-src 'self'; "
        ."object-src 'none'; base-uri 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->is('api/*') || $request->is('media/*')) {
            return $response;
        }

        if ($request->routeIs('tv.youtube')) {
            $headers->set('Content-Security-Policy', self::YOUTUBE_BRIDGE_CSP);
        } else {
            $headers->set('Content-Security-Policy', self::PANEL_CSP);
            $headers->set('X-Frame-Options', 'DENY');
        }

        return $response;
    }
}
