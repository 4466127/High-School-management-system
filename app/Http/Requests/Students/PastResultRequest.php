<?php

namespace App\Http\Requests\Students;

use App\Enums\FinalResult;
use App\Models\GradeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** نتیجه‌ی یک سال درج دستی (مرحله‌ی ۱۴). مسیر با can:students.manage بسته است. */
class PastResultRequest extends FormRequest
{
    /** قاعده‌های مشترک (فرم دانش‌آموز سابق هم برای هر سطر نتیجه از همین استفاده می‌کند) */
    public static function resultRules(string $prefix = ''): array
    {
        return [
            $prefix.'grade_level_id' => ['nullable', 'integer', Rule::exists(GradeLevel::class, 'id')],
            $prefix.'year_name' => ['required', 'regex:/^1[2-4][0-9]{2}$/'],
            $prefix.'school_name' => ['nullable', 'string', 'max:200'],
            $prefix.'total' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            $prefix.'average' => ['nullable', 'numeric', 'min:0', 'max:100'],
            $prefix.'result' => ['nullable', Rule::enum(FinalResult::class)],
            $prefix.'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function rules(): array
    {
        return array_merge(self::resultRules(), [
            'grade_level_id' => ['nullable', 'required_without:grade_name', 'integer', Rule::exists(GradeLevel::class, 'id')],
            'grade_name' => ['nullable', 'string', 'max:100'],
            'subjects' => ['array', 'max:40'],
            'subjects.*.name' => ['nullable', 'string', 'max:100'],
            'subjects.*.mark' => ['nullable', 'required_with:subjects.*.name', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    public function messages(): array
    {
        return [
            'year_name.regex' => __('past_results.year_format'),
            'subjects.*.mark.required_with' => __('past_results.mark_required'),
        ];
    }

    public function attributes(): array
    {
        return __('past_results.attributes');
    }
}
