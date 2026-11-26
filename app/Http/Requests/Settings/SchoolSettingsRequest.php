<?php

namespace App\Http\Requests\Settings;

use App\Enums\SchoolType;
use App\Http\Requests\Students\StudentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolSettingsRequest extends FormRequest
{
    public const WEEKDAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fees_enabled' => $this->boolean('fees_enabled'),
            'school_ledger_enabled' => $this->boolean('school_ledger_enabled'),
            'remove_logo' => $this->boolean('remove_logo'),
            'letter_show_stamp' => $this->boolean('letter_show_stamp'),
            'weekly_holidays' => array_values((array) $this->input('weekly_holidays', [])),
        ]);
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:150'],
            'school_type' => ['required', Rule::enum(SchoolType::class)],
            'province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'header_line' => ['nullable', 'string', 'max:200'],
            'directorate' => ['nullable', 'string', 'max:200'],
            'school_code' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'letter_outgoing_prefix' => ['nullable', 'string', 'max:20'],
            'letter_incoming_prefix' => ['nullable', 'string', 'max:20'],
            'letter_signature_1' => ['nullable', 'string', 'max:100'],
            'letter_signature_2' => ['nullable', 'string', 'max:100'],
            'letter_show_stamp' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
            'fees_enabled' => ['boolean'],
            'school_ledger_enabled' => ['boolean'],
            'pass_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'conditional_max_failed_subjects' => ['required', 'integer', 'min:0', 'max:10'],
            'deprivation_absence_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grade_a_min' => ['required', 'numeric', 'max:100', 'gt:grade_b_min'],
            'grade_b_min' => ['required', 'numeric', 'gt:grade_c_min'],
            'grade_c_min' => ['required', 'numeric', 'gt:pass_percent'],
            'absence_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'weekly_holidays' => ['array'],
            'weekly_holidays.*' => [Rule::in(self::WEEKDAYS)],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => __('validation.phone')];
    }

    public function attributes(): array
    {
        return __('school.attributes');
    }
}
