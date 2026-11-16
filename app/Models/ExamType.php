<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نوع امتحان (ماهوار، چهارونیم‌ماهه، سالانه، …) با نمره‌ی کامل و وزن پیش‌فرض. */
#[Fillable(['name', 'default_max_mark', 'default_weight', 'counts_in_final', 'sort_order', 'is_active'])]
class ExamType extends Model
{
    protected function casts(): array
    {
        return [
            'default_max_mark' => 'decimal:2',
            'default_weight' => 'decimal:2',
            'counts_in_final' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
