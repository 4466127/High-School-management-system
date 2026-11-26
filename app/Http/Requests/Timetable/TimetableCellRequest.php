<?php

namespace App\Http\Requests\Timetable;

use App\Models\Period;
use App\Models\Room;
use App\Models\Staff;
use App\Models\Subject;
use App\Support\SchoolCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** یک خانه‌ی تقسیم اوقات (مسیر با can:timetable.manage بسته است). subject_id خالی یا clear=1 ← خالی کردن خانه. */
class TimetableCellRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'day' => ['required', Rule::in(SchoolCalendar::schoolDays())],
            'period_id' => ['required', 'integer', Rule::exists(Period::class, 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists(Subject::class, 'id')],
            'staff_id' => ['nullable', 'integer', Rule::exists(Staff::class, 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')],
            'note' => ['nullable', 'string', 'max:100'],
            'clear' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('timetable.attributes');
    }
}
