<?php

namespace App\Http\Requests\Exams;

use App\Models\ExamType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'counts_in_final' => $this->boolean('counts_in_final'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function rules(): array
    {
        $type = $this->route('type');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('exam_types', 'name')->ignore($type instanceof ExamType ? $type->id : null)],
            'default_max_mark' => ['required', 'numeric', 'min:1', 'max:999'],
            'counts_in_final' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['boolean'],
        ];
    }

    /** مقدارهای ذخیره؛ وزن جدا ندارد (وزن = نمره‌ی کامل) */
    public function typeData(): array
    {
        $data = $this->validated();

        return $data + ['default_weight' => $data['default_max_mark']];
    }

    public function attributes(): array
    {
        return __('exams.attributes');
    }
}
