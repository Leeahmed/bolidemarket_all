<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    public function handle(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();
        // Fixed valid dummy hash keeps unknown-account checks on the hashing path.
        $valid = Hash::check($password, $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid || $user->disabled_at !== null) {
            throw ValidationException::withMessages(['email' => ['Identifiants invalides.']]);
        }
        if (Hash::needsRehash($user->password)) {
            $user->password = $password;
            $user->save();
        }

        return $user;
    }
}
