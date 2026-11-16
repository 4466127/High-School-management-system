<?php

namespace App\Models;

use App\Enums\FeePeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع فیس (فیس ماهوار، فیس ثبت‌نام، فیس کتاب …). */
#[Fillable(['name', 'period', 'sort_order', 'is_active'])]
class FeeType extends Model
{
    protected function casts(): array
    {
        return ['period' => FeePeriod::class, 'is_active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(FeeRate::class);
    }
}
