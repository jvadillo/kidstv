<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel de administración de la plataforma. Para el resto de adultos responde como si
 * no existiera. Los administradores se nombran desde el servidor (nokoplay:admin).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
