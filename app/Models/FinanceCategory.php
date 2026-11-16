<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** دسته‌ی درآمد (کمک مردمی، …) یا مصرف (قرطاسیه، برق، ترمیم، …). */
#[Fillable(['type', 'name', 'is_active'])]
class FinanceCategory extends Model
{
    protected function casts(): array
    {
        return ['type' => TransactionType::class, 'is_active' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SchoolTransaction::class);
    }
}
