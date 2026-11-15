<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkHistoryRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['from_date', 'to_date'];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:150'],
            'from_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'to_date' => ['nullable', new ValidJalaliDate],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $from = JalaliDate::parse($this->input('from_date'));
            $to = JalaliDate::parse($this->input('to_date'));
            if ($from && $to && $to->lt($from)) {
                $validator->errors()->add('to_date', __('staff.to_before_from'));
            }
        }];
    }

    public function attributes(): array
    {
        return __('staff.history_attributes');
    }
}
