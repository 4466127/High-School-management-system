<?php

namespace App\Models;

use App\Enums\LetterType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** قالب مکتوب با جای‌نشان‌هایی مثل {student_name} (فهرست در LetterService::PLACEHOLDERS). */
#[Fillable(['name', 'type', 'subject', 'body', 'is_active'])]
class LetterTemplate extends Model
{
    protected function casts(): array
    {
        return ['type' => LetterType::class, 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('name');
    }
}
