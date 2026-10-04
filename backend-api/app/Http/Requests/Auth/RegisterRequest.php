<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Rules\BcryptPasswordInput;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'country_code' => ['required', 'string', Rule::exists('countries', 'code')->where('active', true)],
            'phone' => ['required', 'string', new InternationalPhone(is_string($this->country_code) ? $this->country_code : null)],
            'password' => ['required', 'string', new BcryptPasswordInput, Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'role' => ['sometimes', Rule::in([UserRole::CLIENT->value])],
            'email_verified_at' => ['prohibited'],
            'disabled_at' => ['prohibited'],
            'merchant_profile' => ['prohibited'],
        ];
    }
}
