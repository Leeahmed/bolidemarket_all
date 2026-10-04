<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->disabled_at !== null, 403, 'Compte indisponible.');

        return $next($request);
    }
}
