<?php

namespace App\Http\Requests\Library;

use App\Enums\CopyCondition;
use App\Enums\CopySource;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use Illuminate\Validation\Rule;

/** قاعده‌های مشترک مشخصات جلد (ثبت کتاب، افزودن جلد، ویرایش جلد). */
trait CopyRules
{
    /** @return array<string, list<mixed>> */
    protected function copyRules(): array
    {
        return [
            'source' => ['required', Rule::enum(CopySource::class)],
            'condition' => ['required', Rule::enum(CopyCondition::class)],
            'acquired_on' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'price' => ['nullable', new ValidAmount(allowZero: true)],
            'copy_notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
