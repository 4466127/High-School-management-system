<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AcademicYearRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['start_date', 'end_date'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['copy_assignments' => $this->boolean('copy_assignments')]);
    }

    public function rules(): array
    {
        $year = $this->route('year');

        return [
            'name' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'name')->ignore($year)],
            'start_date' => ['required', new ValidJalaliDate],
            'end_date' => ['required', new ValidJalaliDate],
            'copy_from' => [$year ? 'prohibited' : 'nullable', 'integer', Rule::exists('academic_years', 'id')],
            'copy_assignments' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $start = JalaliDate::parse($this->input('start_date'));
            $end = JalaliDate::parse($this->input('end_date'));
            if ($start && $end && $end->lte($start)) {
                $validator->errors()->add('end_date', __('academics.end_before_start'));
            }
        }];
    }

    public function attributes(): array
    {
        return __('academics.year_attributes');
    }
}
