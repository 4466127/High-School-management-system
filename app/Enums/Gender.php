<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** برچسب بسته به جای استفاده: دانش‌آموز «پسر/دختر»، کارمند «مرد/زن». */
enum Gender: string
{
    use HasLabel;

    case Male = 'male';
    case Female = 'female';

    public function studentLabel(): string
    {
        return __('enums.gender_student.'.$this->value);
    }

    public function staffLabel(): string
    {
        return __('enums.gender_staff.'.$this->value);
    }

    /** @return array<string, string> */
    public static function studentOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->studentLabel()])->all();
    }

    /** @return array<string, string> */
    public static function staffOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->staffLabel()])->all();
    }
}
