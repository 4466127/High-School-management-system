<?php

namespace App\Http\Requests\Letters;

use App\Enums\LetterDirection;
use App\Enums\LetterType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** مکتوب صادره (پیش‌نویس). مسیر با can:letters.manage بسته است. */
class LetterRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['letter_date'];
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LetterType::class)],
            'letter_date' => ['required', new ValidJalaliDate],
            'recipient' => ['nullable', 'string', 'max:200'],
            'organization' => ['nullable', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'letter_template_id' => ['nullable', Rule::exists('letter_templates', 'id')],
            'student_id' => ['nullable', Rule::exists('students', 'id')],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')],
            'reply_to_id' => ['nullable', Rule::exists('letters', 'id')->where('direction', LetterDirection::Incoming->value)],
        ];
    }

    public function attributes(): array
    {
        return __('letters.attributes');
    }
}
