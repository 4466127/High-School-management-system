<?php

namespace App\Http\Requests\Teaching;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;

/** ویرایش یک موضوع پلان تدریس (مجوز در PlanController با TeachingPlanPolicy). */
class PlanItemRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item')->plan);
    }

    protected function jalaliDateFields(): array
    {
        return ['planned_from', 'planned_to'];
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:200'],
            'objectives' => ['nullable', 'string', 'max:1000'],
            'week_no' => ['nullable', 'integer', 'min:1', 'max:60'],
            'planned_hours' => ['nullable', 'integer', 'min:0', 'max:40'],
            'planned_from' => ['nullable', new ValidJalaliDate],
            'planned_to' => ['nullable', new ValidJalaliDate],
        ];
    }

    public function attributes(): array
    {
        return __('teaching.attributes');
    }
}
