<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** حاضری و نمره‌ی اختیاری یک شاگرد در فعالیت عملی (مرحله‌ی ۱۸). */
class PracticalActivityStudent extends Model
{
    protected $table = 'practical_activity_student';

    protected $fillable = ['practical_activity_id', 'enrollment_id', 'present', 'score', 'note'];

    protected function casts(): array
    {
        return ['present' => 'boolean', 'score' => 'decimal:2'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
