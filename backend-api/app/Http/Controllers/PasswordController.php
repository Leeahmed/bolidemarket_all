<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json(['data' => ['message' => 'Si ce compte existe, un lien de réinitialisation sera envoyé.']]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->password = $password;
                $user->remember_token = Str::random(60);
                $user->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['Lien de réinitialisation invalide ou expiré.']]);
        }

        return response()->json(['data' => ['message' => 'Mot de passe réinitialisé.']]);
    }
}
