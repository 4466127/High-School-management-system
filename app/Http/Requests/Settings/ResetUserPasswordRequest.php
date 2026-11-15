<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resetPassword', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['password' => __('users.new_password')];
    }
}
