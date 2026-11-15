<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/** برچسب فارسی هر مقدار از lang/fa/enums.php — کلید: نام enum به snake_case، سپس مقدار. */
trait HasLabel
{
    public function label(): string
    {
        return __('enums.'.Str::snake(class_basename(static::class)).'.'.$this->value);
    }

    /** @return array<string, string> مقدار => برچسب (برای select) */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
