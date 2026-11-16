<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ورق حاضری یک بخش در یک روز (یکتا روی بخش و تاریخ). تغییرها فقط از راه App\Services\AttendanceService. */
#[Fillable(['section_id', 'date', 'taken_by', 'taken_at', 'updated_by'])]
class AttendanceSession extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'taken_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
