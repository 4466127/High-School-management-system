<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نتیجه‌ی نهایی سال: کامیاب، مشروط، ناکام، محروم. */
enum FinalResult: string
{
    use HasLabel;

    case Passed = 'passed';
    case Conditional = 'conditional';
    case Failed = 'failed';
    case Deprived = 'deprived';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Passed => 'text-bg-success',
            self::Conditional => 'text-bg-warning',
            self::Failed => 'text-bg-danger',
            self::Deprived => 'text-bg-dark',
        };
    }
}
