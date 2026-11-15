<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** حذف (نرم) همیشه با دلیل. مجوز را route middleware و Policy در کنترلر بررسی می‌کنند. */
class DeleteWithReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    public function attributes(): array
    {
        return ['reason' => __('students.reason')];
    }
}
