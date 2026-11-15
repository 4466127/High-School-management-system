<?php

namespace App\Models;

use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** سرپرست؛ یک سرپرست می‌تواند به چند دانش‌آموز (خواهر و برادر) وصل باشد. */
#[Fillable(['name', 'phone', 'phone2', 'occupation', 'address'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot(['relation', 'is_primary'])->withTimestamps();
    }
}
