<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نمره‌ی امتحان مشروطی (از ۱۰۰) در یک مضمون ناکام. تغییرها فقط از راه App\Services\ReexamService. */
#[Fillable(['enrollment_id', 'subject_id', 'mark', 'is_absent', 'entered_by', 'updated_by'])]
class ReexamMark extends Model
{
    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
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
