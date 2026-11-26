<?php

namespace App\Http\Requests\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ثبت دانش‌آموز سابق (فارغ سال‌های قبل که در سیستم نبود) با نتیجه‌ی سال‌هایش — مرحله‌ی ۱۴.
 * مسیر با can:students.manage بسته است.
 */
class FormerStudentRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public const STATUSES = [StudentStatus::Graduated, StudentStatus::Transferred, StudentStatus::Dropped];

    protected function jalaliDateFields(): array
    {
        return ['birth_date', 'left_on', 'certificate_date'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['admission_no' => trim((string) $this->input('admission_no'))]);
    }

    public function rules(): array
    {
        $rules = [
            'admission_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('students', 'admission_no')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'tazkira_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'notes' => ['nullable', 'string', 'max:2000'],

            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, self::STATUSES))],
            'left_on' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'reason' => ['nullable', 'string', 'max:500'],
            'certificate_no' => ['nullable', 'string', 'max:100'],
            'certificate_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'certificate_note' => ['nullable', 'string', 'max:500'],

            'results' => ['array', 'max:12'],
        ];
        foreach (PastResultRequest::resultRules('results.*.') as $key => $rule) {
            $rules[$key] = $key === 'results.*.year_name' ? ['nullable', 'regex:/^1[2-4][0-9]{2}$/', 'required_with:results.*.average,results.*.result'] : $rule;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('validation.phone'),
            'admission_no.regex' => __('students.admission_no_format'),
            'results.*.year_name.regex' => __('past_results.year_format'),
            'results.*.year_name.required_with' => __('past_results.year_required'),
        ];
    }

    public function attributes(): array
    {
        return __('past_results.attributes') + __('import.attributes') + ['status' => __('students.status'), 'left_on' => __('archive.left_on')];
    }

    public function studentData(): array
    {
        return collect($this->validated())->only([
            'admission_no', 'first_name', 'last_name', 'father_name', 'grandfather_name', 'gender', 'birth_date', 'tazkira_no', 'phone', 'province', 'notes',
        ])->all();
    }

    public function leavingData(): array
    {
        return collect($this->validated())->only(['status', 'left_on', 'reason', 'certificate_no', 'certificate_date', 'certificate_note'])->all();
    }

    public function resultsData(): array
    {
        return array_values($this->validated()['results'] ?? []);
    }
}
