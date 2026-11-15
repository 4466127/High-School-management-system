<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** صنف (اول تا دوازدهم یا هر صنف دیگر). sort_order صنف بعدی را برای ارتقا تعیین می‌کند. */
#[Fillable(['name', 'sort_order', 'is_active'])]
class GradeLevel extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** مضامین این صنف به ترتیب کارنامه */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** صنف بعدی فعال (برای ارتقا)؛ null یعنی صنف آخر (فارغ). */
    public function next(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('sort_order', '>', $this->sort_order)
            ->orderBy('sort_order')
            ->first();
    }
}
