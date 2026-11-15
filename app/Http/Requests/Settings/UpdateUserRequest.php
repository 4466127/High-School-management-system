<?php

namespace App\Http\Requests\Settings;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->route('user'))],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at'),
                Rule::unique('users', 'staff_id')->ignore($this->route('user'))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /** مدیر سیستم نمی‌تواند نقش «مدیر سیستم» را از خودش بگیرد. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                if (! $target->is($this->user()) || ! $this->user()->isAdmin()) {
                    return;
                }

                $adminRoleId = Role::where('key', config('permissions.admin_role'))->value('id');
                if (! in_array($adminRoleId, array_map('intval', (array) $this->input('roles')), true)) {
                    $validator->errors()->add('roles', __('users.cannot_remove_own_admin'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return __('users.attributes');
    }

    public function messages(): array
    {
        return ['username.regex' => __('users.username_format')];
    }
}
