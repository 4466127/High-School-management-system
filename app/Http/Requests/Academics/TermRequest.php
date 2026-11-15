<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TermRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['start_date', 'end_date'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', new ValidJalaliDate],
            'end_date' => ['nullable', new ValidJalaliDate],
            'sort_order' => ['required', 'integer', 'min:1', 'max:20'],
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
        return __('academics.term_attributes');
    }
}
