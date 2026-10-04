<?php

namespace App\Http\Controllers;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\RegisterClient;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterClient $action): JsonResponse
    {
        return (new UserResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $user = $action->handle($request->validated('email'), $request->validated('password'));
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return (new UserResource($user))->response();
        }

        return $this->issueToken($user, $request);
    }

    public function token(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        return $this->issueToken($action->handle($request->validated('email'), $request->validated('password')), $request);
    }

    private function issueToken(User $user, LoginRequest $request): JsonResponse
    {
        $expires = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken($request->validated('device_name', 'api-client'), ['*'], $expires);

        return response()->json(['data' => [
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expires->toISOString(),
        ]]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } elseif ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
