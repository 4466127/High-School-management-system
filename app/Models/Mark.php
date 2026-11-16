<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نمره‌ی یک دانش‌آموز در یک مضمون در یک امتحان. تغییرها فقط از راه App\Services\MarkService. */
#[Fillable(['exam_id', 'enrollment_id', 'subject_id', 'mark', 'is_absent', 'note', 'entered_by', 'updated_by'])]
class Mark extends Model
{
    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
