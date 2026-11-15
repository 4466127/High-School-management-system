<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** ثبت رویدادها — فقط افزودن. برای نوشتن از App\Services\ActivityLogger استفاده شود. */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'description', 'old_values', 'new_values', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        $key = 'activity.actions.'.$this->action;

        return __($key) !== $key ? __($key) : $this->action;
    }
}
