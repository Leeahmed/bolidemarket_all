<?php

use App\Exceptions\CommerceConflict;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\RequireRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request) => null);
        $middleware->prepend(RequestId::class);
        $middleware->alias([
            'role' => RequireRole::class,
            'active' => EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };
            $code = match ($status) {
                401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED', 419 => 'CSRF_MISMATCH',
                422 => 'VALIDATION_FAILED', 429 => 'RATE_LIMITED', default => 'SERVER_ERROR',
            };
            $message = match ($status) {
                401 => 'Authentification requise.', 403 => 'Accès refusé.', 404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.', 419 => 'Session ou jeton CSRF invalide.',
                422 => 'Données invalides.', 429 => 'Trop de tentatives. Réessayez plus tard.',
                default => 'Une erreur est survenue.',
            };
            $error = ['code' => $code, 'message' => $message];
            if ($e instanceof CommerceConflict) {
                $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            }
            if ($e instanceof ValidationException) {
                $error['fields'] = $e->errors();
            }
            $id = $request->attributes->get('request_id', (string) Str::uuid());
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(['error' => $error, 'request_id' => $id], $status, $headers)
                ->header('X-Request-ID', $id);
        });
    })->create();
