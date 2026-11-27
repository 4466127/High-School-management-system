<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** خطر اصلی ماده‌ی کیمیاوی (مرحله‌ی ۱۸)؛ برای نگه‌داری جدا و هشدار. */
enum ChemicalHazard: string
{
    use HasLabel;

    case None = 'none';
    case Flammable = 'flammable';
    case Toxic = 'toxic';
    case Corrosive = 'corrosive';
    case Oxidizer = 'oxidizer';
    case Irritant = 'irritant';
    case Explosive = 'explosive';

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'text-bg-light',
            self::Flammable => 'text-bg-danger',
            self::Toxic => 'text-bg-dark',
            self::Corrosive => 'text-bg-warning',
            self::Oxidizer => 'text-bg-warning',
            self::Irritant => 'text-bg-info',
            self::Explosive => 'text-bg-danger',
        };
    }
}
