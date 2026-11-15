<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8), 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => __('users.current_password'),
            'password' => __('users.new_password'),
        ];
    }
}
