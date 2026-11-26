<?php

namespace App\Http\Requests\Letters;

use App\Enums\LetterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('letter_templates', 'name')->ignore($this->route('template'))],
            'type' => ['required', Rule::enum(LetterType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('letters.attributes');
    }
}
