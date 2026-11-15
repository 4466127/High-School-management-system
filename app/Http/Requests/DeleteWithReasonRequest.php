<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

/**
 * حذف (نرم) همیشه با دلیل.
 * دانش‌آموز: مجوز با StudentPolicy پیش از اعتبارسنجی؛ کارمند: route middleware (can:staff.manage).
 */
class DeleteWithReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student ? $this->user()->can('delete', $student) : true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return ['reason' => __('students.reason')];
    }
}
