<?php

namespace App\Http\Requests\Students;

use App\Enums\StudentStatus;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** مرحله‌ی ۳: فقط فعال/غیرفعال کردن پرونده (با دلیل). */
class StudentStatusRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    protected function jalaliDateFields(): array
    {
        return ['date'];
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([StudentStatus::Active->value, StudentStatus::Inactive->value])],
            'reason' => ['nullable', 'required_if:status,inactive', 'string', 'max:500'],
            'date' => ['nullable', new ValidJalaliDate(notFuture: true)],
        ];
    }

    public function attributes(): array
    {
        return ['status' => __('students.status'), 'reason' => __('students.reason'), 'date' => __('students.status_date')];
    }
}
