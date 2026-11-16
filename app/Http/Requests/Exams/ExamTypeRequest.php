<?php

namespace App\Http\Requests\Exams;

use App\Models\ExamType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'default_weight' => ['required', 'numeric', 'min:0', 'max:999'],
            'counts_in_final' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->boolean('counts_in_final') && (float) $this->input('default_weight') <= 0 && ! $validator->errors()->has('default_weight')) {
                $validator->errors()->add('default_weight', __('exams.weight_required'));
            }
        }];
    }

    public function attributes(): array
    {
        return __('exams.attributes');
    }
}
