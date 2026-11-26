<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** اعلان مکتب. فقط از راه AnnouncementController (با ثبت رویداد) تغییر می‌کند؛ حذف نمی‌شود، بایگانی می‌شود. */
#[Fillable(['title', 'body', 'audience', 'status', 'is_pinned', 'published_at', 'expires_on', 'created_by', 'updated_by'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'status' => AnnouncementStatus::class,
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_on' => 'date',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** منتشرشده و هنوز کهنه نشده */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AnnouncementStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString()));
    }

    /** اعلان‌هایی که این کاربر می‌بیند. مدیر اعلان‌ها همه را؛ «معلمان» فقط معلم‌ها؛ بقیه برای همه‌ی کارکنان. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('announcements.manage') || $user->hasRole(Role::Teacher)) {
            return $query;
        }

        return $query->where('audience', '!=', AnnouncementAudience::Teachers);
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isActive(): bool
    {
        return $this->status === AnnouncementStatus::Published && ($this->expires_on === null || $this->expires_on->endOfDay()->isFuture());
    }

    public function visibleTo(User $user): bool
    {
        return static::query()->whereKey($this->id)->visibleTo($user)->exists();
    }
}
