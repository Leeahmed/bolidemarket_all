<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthenticationException;
        }
        $allowed = array_filter(array_map(UserRole::tryFrom(...), $roles));
        abort_unless($user->disabled_at === null && in_array($user->role, $allowed, true), 403);

        return $next($request);
    }
}
