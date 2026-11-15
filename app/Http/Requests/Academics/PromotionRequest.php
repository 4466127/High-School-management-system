<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['date'];
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'target_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'promote_section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'repeat_section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'date' => ['nullable', new ValidJalaliDate],
            'decisions' => ['required', 'array'],
            'decisions.*' => [Rule::in([EnrollmentService::PROMOTE, EnrollmentService::REPEAT, EnrollmentService::GRADUATE, EnrollmentService::SKIP])],
        ];
    }

    public function attributes(): array
    {
        return __('academics.promotion_attributes');
    }
}
