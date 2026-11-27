<?php

namespace App\Models;

use App\Enums\LabType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** یک لابراتوار مکتب (مرحله‌ی ۱۸). حذف نمی‌شود؛ غیرفعال می‌شود. */
#[Fillable(['name', 'type', 'room_id', 'staff_id', 'safety_notes', 'notes', 'is_active'])]
class Lab extends Model
{
    protected function casts(): array
    {
        return ['type' => LabType::class, 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabItem::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PracticalActivity::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
