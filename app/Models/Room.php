<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** اتاق درسی، لابراتوار، تالار — مرحله‌ی ۱۵. */
#[Fillable(['name', 'type', 'capacity', 'notes', 'is_active'])]
class Room extends Model
{
    protected function casts(): array
    {
        return ['type' => RoomType::class, 'is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
