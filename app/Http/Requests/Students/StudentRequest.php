<?php

namespace App\Http\Requests\Students;

use App\Enums\Gender;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\Student;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ثبت و ویرایش دانش‌آموز. سرپرست اصلی فقط هنگام ثبت (guardian.*). */
class StudentRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public const PHONE_REGEX = '/^[0-9+\-\s()]{6,30}$/';

    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            ? $this->user()->can('update', $student)
            : $this->user()->can('create', Student::class);
    }

    protected function jalaliDateFields(): array
    {
        return ['birth_date', 'admission_date'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['admission_no' => trim((string) $this->input('admission_no'))]);
    }

    public function rules(): array
    {
        $student = $this->route('student');
        $creating = ! $student instanceof Student;

        return [
            'admission_no' => [$creating ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/',
                Rule::unique('students', 'admission_no')->ignore($student)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'tazkira_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'regex:'.self::PHONE_REGEX],
            'province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'district' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'admission_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],

            'guardian' => [$creating ? 'nullable' : 'prohibited', 'array'],
            'guardian.name' => ['nullable', 'required_with:guardian.relation,guardian.phone', 'string', 'max:150'],
            'guardian.relation' => ['nullable', 'required_with:guardian.name', 'string', 'max:50'],
            'guardian.phone' => ['nullable', 'regex:'.self::PHONE_REGEX],
            'guardian.phone2' => ['nullable', 'regex:'.self::PHONE_REGEX],
            'guardian.occupation' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** فیلدهای جدول students (بدون عکس و سرپرست) */
    public function studentData(): array
    {
        return collect($this->validated())
            ->only((new Student)->getFillable())
            ->all();
    }

    public function guardianData(): ?array
    {
        $guardian = $this->validated('guardian');

        return is_array($guardian) && ($guardian['name'] ?? '') !== '' ? $guardian : null;
    }

    public function attributes(): array
    {
        return __('students.attributes');
    }

    public function messages(): array
    {
        return [
            'admission_no.regex' => __('students.admission_no_format'),
            'phone.regex' => __('validation.phone'),
            'guardian.phone.regex' => __('validation.phone'),
            'guardian.phone2.regex' => __('validation.phone'),
        ];
    }
}
