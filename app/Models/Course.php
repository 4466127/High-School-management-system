<?php

namespace App\Models;

use App\Enums\CourseSeason;
use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** کورس تقویتی (کامپیوتر، انگلیسی، ساینس، …) با دوره‌ی زمانی خودش؛ جدا از سال تحصیلی و مالی مکتب. */
#[Fillable(['name', 'field', 'season', 'start_date', 'end_date', 'status', 'description', 'created_by'])]
class Course extends Model
{
    protected function casts(): array
    {
        return [
            'season' => CourseSeason::class,
            'status' => CourseStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CourseGroup::class)->orderBy('shift')->orderBy('name');
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(CourseParticipant::class, CourseGroup::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CourseLedgerEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** کورس‌هایی که کاربر معلم یکی از گروه‌های آن است */
    public function scopeTaughtBy(Builder $query, ?int $staffId): Builder
    {
        return $query->whereHas('groups', fn ($q) => $q->where('staff_id', $staffId ?? 0));
    }

    /** معلمان کورس (از گروه‌ها) */
    public function teacherIds(): array
    {
        return $this->groups->pluck('staff_id')->filter()->unique()->values()->all();
    }
}
