<?php

namespace App\Http\Requests\Academics;

use App\Enums\Shift;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
{
    /** مجوز پیش از اعتبارسنجی بررسی شود (کاربر بی‌مجوز ۴۰۳ ببیند، نه خطای فرم) */
    public function authorize(): bool
    {
        return $this->user()->can('manage', Section::class);
    }

    public function rules(): array
    {
        /** @var Section|null $section */
        $section = $this->route('section');
        $yearId = $section?->academic_year_id ?? $this->input('academic_year_id');

        return [
            'academic_year_id' => [$section ? 'prohibited' : 'required', 'integer', Rule::exists('academic_years', 'id')],
            'grade_level_id' => ['required', 'integer', Rule::exists('grade_levels', 'id')],
            'name' => ['required', 'string', 'max:20',
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $yearId)
                    ->where('grade_level_id', $this->input('grade_level_id'))
                    ->where('shift', $this->input('shift'))
                    ->ignore($section)],
            'shift' => ['required', Rule::enum(Shift::class)],
            'class_teacher_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at')],
            'room' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => __('academics.section_exists')];
    }

    public function attributes(): array
    {
        return __('academics.section_attributes');
    }
}
