<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * ثبت رویدادهای حساس: ورود/خروج، تغییر کاربران و نقش‌ها، و در مرحله‌های بعد نمره، حاضری، مالی و تنظیمات.
 * رمز و توکن‌ها هرگز ذخیره نمی‌شوند.
 */
class ActivityLogger
{
    private const SECRET_KEYS = ['password', 'password_confirmation', 'current_password', 'remember_token'];

    public function __construct(private readonly Request $request) {}

    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $old = [],
        array $new = [],
        ?User $user = null,
    ): ActivityLog {
        $user ??= $this->request->user();

        return ActivityLog::create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'old_values' => $this->clean($old),
            'new_values' => $this->clean($new),
            'ip_address' => $this->request->ip(),
        ]);
    }

    private function clean(array $values): ?array
    {
        $values = Arr::except($values, self::SECRET_KEYS);

        return $values === [] ? null : $values;
    }
}
