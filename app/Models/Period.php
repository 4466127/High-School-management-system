<?php

namespace App\Models;

use App\Enums\Shift;
use App\Support\Clock;
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

    /** اول قبل از ظهر، بعد بعد از ظهر؛ در هر نوبت به ترتیب ساعت */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('shift = ? desc', [Shift::Morning->value])->orderBy('sort_order')->orderBy('start_time');
    }

    /** «۸:۰۰ – ۸:۴۰» (۱۲ ساعته، ارقام فارسی) */
    public function timeRange(): string
    {
        return Clock::range($this->start_time, $this->end_time);
    }
}
