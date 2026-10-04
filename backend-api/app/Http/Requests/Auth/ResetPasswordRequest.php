<?php

namespace App\Http\Requests\Auth;

use App\Rules\BcryptPasswordInput;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', new BcryptPasswordInput, Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ];
    }
}
