<?php

namespace App\Models;

use App\Enums\Shift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ساعت درسی (یا تفریح) یک نوبت — مرحله‌ی ۱۵. */
#[Fillable(['name', 'shift', 'start_time', 'end_time', 'sort_order', 'is_break', 'is_active'])]
class Period extends Model
{
    protected function casts(): array
    {
        return ['shift' => Shift::class, 'is_break' => 'boolean', 'is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('shift')->orderBy('sort_order')->orderBy('start_time');
    }

    /** «۰۸:۰۰ – ۰۸:۴۰» */
    public function timeRange(): string
    {
        return substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5);
    }
}
