<?php

namespace App\Http\Requests\Auth;

use App\Enums\SchoolType;
use App\Http\Requests\Students\StudentRequest;
use App\Services\SetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** صفحه‌ی راه‌اندازی نصب تازه: مشخصات مکتب و اولین مدیر سیستم */
class SetupRequest extends FormRequest
{
    /** بعد از راه‌اندازی (یا بدون کد) صفحه وجود ندارد؛ پیش از اعتبارسنجی */
    public function authorize(): bool
    {
        abort_unless(app(SetupService::class)->pending(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => mb_strtolower(trim((string) $this->input('username')))]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:20'],
            'school_name' => ['required', 'string', 'max:150'],
            'school_type' => ['required', Rule::enum(SchoolType::class)],
            'province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.phone'), 'username.regex' => __('users.username_format')];
    }

    public function attributes(): array
    {
        return __('school.attributes') + [
            'token' => __('setup.token'),
            'name' => __('setup.admin_name'),
            'username' => __('users.username'),
            'password' => __('users.password'),
        ];
    }
}
