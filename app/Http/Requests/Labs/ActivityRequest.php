<?php

namespace App\Http\Requests\Labs;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\PracticalActivity;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;

/** پلان یا ویرایش فعالیت عملی (مرحله‌ی ۱۸). مجوز دقیق در کنترلر با PracticalActivityPolicy (بعد از دانستن بخش و مضمون). */
class ActivityRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('create', PracticalActivity::class);
    }

    protected function jalaliDateFields(): array
    {
        return ['activity_date'];
    }

    public function rules(): array
    {
        return array_merge($this->isMethod('POST') ? ['assignment' => ['required', 'string', 'regex:/^\d+\|\d+$/']] : [], [
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'activity_date' => ['required', new ValidJalaliDate],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'title' => ['required', 'string', 'max:200'],
            'objectives' => ['nullable', 'string', 'max:1000'],
            'materials_note' => ['nullable', 'string', 'max:1000'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    public function attributes(): array
    {
        return __('labs.attributes');
    }

    /** @return array<string, mixed> */
    public function activityData(): array
    {
        $v = $this->validated();

        return [
            'lab_id' => (int) $v['lab_id'], 'activity_date' => $v['activity_date'], 'period_id' => ($v['period_id'] ?? null) ?: null,
            'title' => $v['title'], 'objectives' => $v['objectives'] ?? null, 'materials_note' => $v['materials_note'] ?? null,
            'max_score' => ($v['max_score'] ?? null) ?: null,
        ];
    }
}
