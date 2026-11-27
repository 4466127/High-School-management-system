<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** فعالیت عملی (تجربه) یک بخش در لابراتوار (مرحله‌ی ۱۸). فقط از راه LabService؛ حذف نمی‌شود (لغو با دلیل). */
#[Fillable(['lab_id', 'period_id', 'activity_date', 'title', 'objectives', 'materials_note', 'max_score'])]
class PracticalActivity extends Model
{
    protected function casts(): array
    {
        return ['status' => ActivityStatus::class, 'activity_date' => 'date', 'max_score' => 'integer'];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(PracticalActivityStudent::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LabItemEvent::class)->oldest('id');
    }
}
