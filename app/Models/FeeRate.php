<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نرخ یک نوع فیس برای یک صنف در یک سال. */
#[Fillable(['fee_type_id', 'academic_year_id', 'grade_level_id', 'amount'])]
class FeeRate extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }
}
