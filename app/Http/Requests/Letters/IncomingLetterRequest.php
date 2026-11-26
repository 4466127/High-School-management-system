<?php

namespace App\Http\Requests\Letters;

use App\Enums\LetterType;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ثبت یا اصلاح مکتوب وارده. اسکن اختیاری فقط هنگام ثبت. */
class IncomingLetterRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->merge(['needs_response' => $this->boolean('needs_response')]);
    }

    protected function jalaliDateFields(): array
    {
        return ['letter_date', 'received_on'];
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LetterType::class)],
            'letter_date' => ['required', new ValidJalaliDate],
            'received_on' => ['required', new ValidJalaliDate(notFuture: true)],
            'recipient' => ['nullable', 'string', 'max:200'],
            'organization' => ['required', 'string', 'max:200'],
            'external_number' => ['nullable', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'needs_response' => ['boolean'],
            'student_id' => ['nullable', Rule::exists('students', 'id')],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')],
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function letterData(): array
    {
        $data = $this->validated();
        unset($data['scan']);

        return $data;
    }

    public function attributes(): array
    {
        return __('letters.attributes');
    }
}
