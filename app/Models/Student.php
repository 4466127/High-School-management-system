<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Support\PersianText;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * پرونده‌ی دانش‌آموز. صنف و بخش در جدول enrollments (مرحله‌ی ۴) است، نه این‌جا.
 * حذف نرم با دلیل (deleted_reason)؛ برای تغییرها از App\Services\StudentService استفاده شود.
 */
#[Fillable([
    'admission_no', 'first_name', 'last_name', 'father_name', 'grandfather_name', 'gender', 'birth_date',
    'tazkira_no', 'phone', 'province', 'district', 'village', 'address', 'admission_date', 'notes',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /** فیلدهایی که فقط کاربر دارای students.manage می‌بیند (StudentPolicy::viewSensitive) */
    public const SENSITIVE_FIELDS = ['tazkira_no', 'phone', 'province', 'district', 'village', 'address', 'notes'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'birth_date' => 'date',
            'admission_date' => 'date',
            'status_changed_on' => 'date',
            'certificate_date' => 'date',
        ];
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relation', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class)->orderBy('category')->latest();
    }

    /** انتقال‌ها میان مکاتب (مرحله‌ی ۱۳) */
    public function transfers(): HasMany
    {
        return $this->hasMany(StudentTransfer::class)->latest('transfer_date')->latest('id');
    }

    /** نتیجه‌ی سال‌هایی که در سیستم نیست، درج دستی (مرحله‌ی ۱۴) */
    public function pastResults(): HasMany
    {
        return $this->hasMany(StudentPastResult::class)->orderBy('year_name')->orderBy('id');
    }

    /** سوابق تحصیلی: یک سطر برای هر سال */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** شمولیت فعال در سال جاری (اگر هست) */
    public function currentEnrollment(): HasOne
    {
        $year = AcademicYear::current();

        return $this->hasOne(Enrollment::class)
            ->where('academic_year_id', $year?->id ?? 0)
            ->where('status', EnrollmentStatus::Active);
    }

    /** دانش‌آموزانی که در یکی از این بخش‌ها شمولیت فعال دارند (محدوده‌ی معلم) */
    public function scopeInSections(Builder $query, array $sectionIds): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q
            ->whereIn('section_id', $sectionIds ?: [0])
            ->where('status', EnrollmentStatus::Active));
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /** جست‌وجو با نام، نام پدر/پدرکلان، نمبر اساس، تماس یا تذکره؛ هر واژه باید در یکی از این‌ها باشد. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        foreach (PersianText::searchTerms($term) as $word) {
            $like = PersianText::like($word);
            $query->where(fn (Builder $q) => $q
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('father_name', 'like', $like)
                ->orWhere('grandfather_name', 'like', $like)
                ->orWhere('admission_no', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('tazkira_no', 'like', $like));
        }

        return $query;
    }
}
