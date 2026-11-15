<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['key', 'name'])]
class Role extends Model
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** نام فارسی از فایل ترجمه؛ اگر نبود، نام ذخیره‌شده. */
    public function label(): string
    {
        $key = 'roles.'.$this->key;

        return __($key) !== $key ? __($key) : $this->name;
    }
}
