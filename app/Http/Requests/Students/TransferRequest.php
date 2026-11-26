<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** انتقال صادره یا وارده (مسیر با can:students.manage). نوع از نام مسیر: student-transfers.out.* / in.* */
class TransferRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->merge(['clearance_confirmed' => $this->boolean('clearance_confirmed')]);
    }

    protected function jalaliDateFields(): array
    {
        return ['requested_on', 'approved_on', 'transfer_date'];
    }

    public function isIncoming(): bool
    {
        return $this->routeIs('student-transfers.in.*');
    }

    public function rules(): array
    {
        return [
            'other_school' => ['required', 'string', 'max:200'],
            'other_province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'other_district' => ['nullable', 'string', 'max:100'],
            'requested_on' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'approved_on' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'transfer_date' => ['required', new ValidJalaliDate],
            'reason' => ['required', 'string', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'last_grade' => ['nullable', 'string', 'max:100'],
            'clearance_confirmed' => ['boolean'],
            'clearance_note' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'package' => [$this->isIncoming() ? 'nullable' : 'prohibited', 'file', 'max:512', 'mimetypes:application/json,text/plain'],
        ];
    }

    /** خانه‌های جدول student_transfers */
    public function transferData(): array
    {
        $data = $this->validated();
        unset($data['package']);

        return $data;
    }

    public function attributes(): array
    {
        return __('transfers.attributes');
    }
}
