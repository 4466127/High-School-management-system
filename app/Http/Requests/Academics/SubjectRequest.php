<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // فرم‌ها همیشه is_active=0 پنهان + چک‌باکس 1 دارند
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($this->route('subject'))],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('academics.subject_attributes');
    }
}
