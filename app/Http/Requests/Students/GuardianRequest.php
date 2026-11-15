<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class GuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'relation' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'phone2' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'occupation' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('students.guardian_attributes');
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.phone'), 'phone2.regex' => __('validation.phone')];
    }
}
