<?php

namespace App\Http\Requests\Staff;

use App\Enums\EmploymentStatus;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffStatusRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['date'];
    }

    public function rules(): array
    {
        return [
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'reason' => ['nullable', 'required_if:employment_status,left', 'string', 'max:500'],
            'date' => ['nullable', new ValidJalaliDate(notFuture: true)],
        ];
    }

    public function attributes(): array
    {
        return [
            'employment_status' => __('staff.employment_status'),
            'reason' => __('staff.reason'),
            'date' => __('staff.status_date'),
        ];
    }
}
