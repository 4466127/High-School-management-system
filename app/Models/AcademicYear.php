<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'start_date', 'end_date', 'status'])]
class AcademicYear extends Model
{
    /** سال جاری در همین درخواست (یک بار خوانده می‌شود) */
    private static ?AcademicYear $currentCache = null;

    private static bool $currentLoaded = false;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        if (! self::$currentLoaded) {
            self::$currentCache = static::query()->where('is_current', true)->first();
            self::$currentLoaded = true;
        }

        return self::$currentCache;
    }

    public static function forgetCurrent(): void
    {
        self::$currentCache = null;
        self::$currentLoaded = false;
    }

    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
