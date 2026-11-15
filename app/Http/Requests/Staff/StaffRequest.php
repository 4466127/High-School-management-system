<?php

namespace App\Http\Requests\Staff;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Http\Requests\Students\StudentRequest;
use App\Models\Staff;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['hire_date'];
    }

    public function rules(): array
    {
        return [
            'staff_type' => ['required', Rule::enum(StaffType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'education' => ['nullable', Rule::enum(EducationLevel::class)],
            'specialization' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'hire_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'position' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function staffData(): array
    {
        return collect($this->validated())->only((new Staff)->getFillable())->all();
    }

    public function attributes(): array
    {
        return __('staff.attributes');
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.phone')];
    }
}
