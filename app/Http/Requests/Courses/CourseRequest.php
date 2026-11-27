<?php

namespace App\Http\Requests\Courses;

use App\Enums\CourseSeason;
use App\Enums\CourseStatus;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\Course;
use App\Rules\ValidJalaliDate;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** ساخت و ویرایش کورس (courses.manage؛ مجوز پیش از اعتبارسنجی — مرحله‌ی ۱۹). */
class CourseRequest extends FormRequest
{
    use ConvertsJalaliDates;

    public function authorize(): bool
    {
        return Gate::allows('manage', Course::class);
    }

    protected function jalaliDateFields(): array
    {
        return ['start_date', 'end_date'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'field' => ['required', 'string', 'max:100'],
            'season' => ['required', Rule::enum(CourseSeason::class)],
            'start_date' => ['required', new ValidJalaliDate],
            'end_date' => ['nullable', new ValidJalaliDate],
            'status' => ['required', Rule::enum(CourseStatus::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $start = JalaliDate::parse($this->input('start_date'));
            $end = JalaliDate::parse($this->input('end_date'));
            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('end_date', __('academics.end_before_start'));
            }
        }];
    }

    public function attributes(): array
    {
        return __('courses.attributes');
    }
}
