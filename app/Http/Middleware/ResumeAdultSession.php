<?php

namespace App\Http\Middleware;

use App\Services\AdultSessions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResumeAdultSession
{
    public function __construct(private AdultSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->sessions->resume($request);

        return $next($request);
    }
}
