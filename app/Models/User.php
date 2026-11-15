<?php

namespace App\Models;

use App\Enums\Role as RoleKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'username', 'staff_id', 'password', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** کلیدهای مجوز کاربر (از نقش‌ها + مجوزهای اضافی)؛ در هر درخواست یک بار خوانده می‌شود. */
    private ?array $permissionKeysCache = null;

    private ?array $taughtSectionIdsCache = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** نام کاربری همیشه با حروف کوچک ذخیره می‌شود. */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    /** کارمند/معلمی که این حساب به او تعلق دارد (اختیاری؛ برای محدوده‌ی معلم در مرحله‌های بعد) */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** مجوزهای اضافی که مستقیم به همین کاربر داده شده است. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(RoleKey|string $role): bool
    {
        $key = $role instanceof RoleKey ? $role->value : $role;

        return $this->roles->contains('key', $key);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(config('permissions.admin_role'));
    }

    public function hasPermission(string $key): bool
    {
        return $this->isAdmin() || in_array($key, $this->permissionKeys(), true);
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->permissionKeysCache === null) {
            $this->loadMissing('roles.permissions', 'permissions');

            $this->permissionKeysCache = $this->roles
                ->flatMap->permissions
                ->merge($this->permissions)
                ->pluck('key')
                ->unique()
                ->values()
                ->all();
        }

        return $this->permissionKeysCache;
    }

    /**
     * بخش‌های سال جاری که این کاربر (از راه کارمند وصل‌شده) در آن‌ها نگران صنف یا معلم مضمون است.
     * محدوده‌ی معلم برای دیدن دانش‌آموزان، حاضری و نمرات.
     *
     * @return list<int>
     */
    public function taughtSectionIds(): array
    {
        if ($this->taughtSectionIdsCache !== null) {
            return $this->taughtSectionIdsCache;
        }

        $year = AcademicYear::current();
        if (! $this->staff_id || ! $year) {
            return $this->taughtSectionIdsCache = [];
        }

        $asClassTeacher = Section::query()
            ->where('academic_year_id', $year->id)
            ->where('class_teacher_id', $this->staff_id)
            ->pluck('id');

        $asSubjectTeacher = TeacherAssignment::query()
            ->where('staff_id', $this->staff_id)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
            ->pluck('section_id');

        return $this->taughtSectionIdsCache = $asClassTeacher->merge($asSubjectTeacher)->unique()->values()->all();
    }

    /** بعد از تغییر نقش یا مجوز همین شیء صدا شود. */
    public function flushPermissionCache(): void
    {
        $this->taughtSectionIdsCache = null;
        $this->permissionKeysCache = null;
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
    }
}
