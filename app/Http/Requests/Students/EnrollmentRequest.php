<?php

namespace App\Http\Requests\Students;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * کارهای شمولیت از پرونده‌ی دانش‌آموز: شامل کردن (enroll)، تغییر بخش (move)، انتقال/ترک (leave).
 * نوع کار از نام مسیر معلوم می‌شود.
 */
class EnrollmentRequest extends FormRequest
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
        return match ($this->route()->getName()) {
            'students.enrollment.leave' => [
                'status' => ['required', Rule::in([EnrollmentStatus::Transferred->value, EnrollmentStatus::Dropped->value])],
                'reason' => ['required', 'string', 'max:500'],
                'date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            ],
            'students.enrollment.move' => [
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
                'reason' => ['nullable', 'string', 'max:500'],
            ],
            default => [
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
                'date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            ],
        };
    }

    public function attributes(): array
    {
        return __('academics.enrollment_attributes');
    }
}
