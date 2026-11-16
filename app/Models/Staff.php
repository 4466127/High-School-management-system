<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Support\PersianText;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** معلم یا کارمند (یک جدول برای همه؛ نوع در staff_type). */
#[Fillable([
    'staff_type', 'first_name', 'last_name', 'father_name', 'gender', 'phone', 'education',
    'specialization', 'address', 'hire_date', 'position', 'notes',
])]
class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'staff_type' => StaffType::class,
            'gender' => Gender::class,
            'education' => EducationLevel::class,
            'employment_status' => EmploymentStatus::class,
            'hire_date' => 'date',
            'status_changed_on' => 'date',
        ];
    }

    public function workHistory(): HasMany
    {
        return $this->hasMany(StaffWorkHistory::class)->orderByDesc('from_date');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'staff_id');
    }

    /** بخش‌هایی که این کارمند نگران صنف آن‌هاست */
    public function classSections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_teacher_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    /** حساب کاربری وصل‌شده (اختیاری) */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        foreach (PersianText::searchTerms($term) as $word) {
            $like = PersianText::like($word);
            $query->where(fn (Builder $q) => $q
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('father_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('specialization', 'like', $like)
                ->orWhere('position', 'like', $like));
        }

        return $query;
    }
}
