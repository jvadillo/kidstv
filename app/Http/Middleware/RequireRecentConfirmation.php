<?php

namespace App\Http\Middleware;

use App\Services\AdultSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cambios sensibles (PIN, correo, eliminación de la cuenta, exportación) exigen
 * haber revalidado el correo en los últimos minutos.
 */
class RequireRecentConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AdultSessions::recentlyConfirmed($request)) {
            return $next($request);
        }

        $intended = $request->isMethod('GET') ? $request->fullUrl() : url()->previous(route('account.show'));
        $request->session()->put('url.intended', $intended);

        return redirect()->route('confirm.show');
    }
}
