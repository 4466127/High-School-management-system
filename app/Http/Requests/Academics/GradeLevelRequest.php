<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeLevelRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $grade = $this->route('grade');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('grade_levels', 'name')->ignore($grade)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('grade_levels', 'sort_order')->ignore($grade)],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('academics.grade_attributes');
    }
}
