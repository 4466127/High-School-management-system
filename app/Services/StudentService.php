<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use App\Support\ModelChanges;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * همه‌ی تغییرهای پرونده‌ی دانش‌آموز از این‌جا: هر عملیات داخل transaction و با ثبت رویداد.
 */
class StudentService
{
    private const PHOTO_DIR = 'students/photos';

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  فیلدهای Student (تاریخ‌ها میلادی)
     * @param  array<string, mixed>|null  $guardian  سرپرست اصلی: name, relation, phone, phone2, occupation
     */
    public function create(array $data, ?UploadedFile $photo = null, ?array $guardian = null): Student
    {
        $storedPhoto = $photo ? PrivateFiles::store($photo, self::PHOTO_DIR) : null;

        try {
            return DB::transaction(function () use ($data, $storedPhoto, $guardian) {
                $student = new Student($data);
                $student->admission_no = ($data['admission_no'] ?? '') !== '' ? $data['admission_no'] : $this->nextAdmissionNo();
                $student->status = StudentStatus::Active;
                $student->photo_path = $storedPhoto;
                $student->save();

                if ($guardian && ($guardian['name'] ?? '') !== '') {
                    $this->attachGuardian($student, $guardian + ['is_primary' => true], log: false);
                }

                $this->logger->log('student.created', $student, $student->full_name.' — '.$student->admission_no,
                    new: ['admission_no' => $student->admission_no, 'name' => $student->full_name, 'father_name' => $student->father_name]);

                return $student;
            });
        } catch (\Throwable $e) {
            PrivateFiles::delete($storedPhoto);
            throw $e;
        }
    }

    public function update(Student $student, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Student
    {
        $oldPhoto = $student->photo_path;
        $newPhoto = $photo ? PrivateFiles::store($photo, self::PHOTO_DIR) : null;

        DB::transaction(function () use ($student, $data, $newPhoto, $removePhoto) {
            $student->fill($data);
            if ($newPhoto) {
                $student->photo_path = $newPhoto;
            } elseif ($removePhoto) {
                $student->photo_path = null;
            }

            [$old, $new] = ModelChanges::capture($student);
            $student->save();

            if ($new !== []) {
                $this->logger->log('student.updated', $student, $student->full_name, $old, $new);
            }
        });

        if ($oldPhoto && $oldPhoto !== $student->photo_path) {
            PrivateFiles::delete($oldPhoto);
        }

        return $student;
    }

    /** مرحله‌ی ۳: فقط فعال/غیرفعال. انتقال و ترک تحصیل در مرحله‌ی ۴ همراه شمولیت سالانه. */
    public function changeStatus(Student $student, StudentStatus $status, ?string $reason, ?string $date): void
    {
        DB::transaction(function () use ($student, $status, $reason, $date) {
            $old = ['status' => $student->status->value, 'status_reason' => $student->status_reason];

            $student->status = $status;
            $student->status_reason = $reason;
            $student->status_changed_on = $date ?? now()->toDateString();
            $student->save();

            $this->logger->log('student.status_changed', $student, $student->full_name.($reason ? ' — '.$reason : ''),
                $old, ['status' => $status->value, 'status_reason' => $reason]);
        });
    }

    /** حذف نرم با دلیل؛ پرونده قابل بازگرداندن است. */
    public function delete(Student $student, string $reason): void
    {
        DB::transaction(function () use ($student, $reason) {
            $student->deleted_reason = $reason;
            $student->save();
            $student->delete();

            $this->logger->log('student.deleted', $student, $student->full_name.' — '.$reason);
        });
    }

    public function restore(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $student->restore();
            $student->deleted_reason = null;
            $student->save();

            $this->logger->log('student.restored', $student, $student->full_name);
        });
    }

    /**
     * وصل کردن سرپرست. اگر سرپرستی با همین نام و شماره‌ی تماس هست (برای خواهر/برادر)، همان وصل می‌شود.
     *
     * @param  array<string, mixed>  $data  name, relation, phone, phone2, occupation, address, is_primary
     */
    public function attachGuardian(Student $student, array $data, bool $log = true): Guardian
    {
        return DB::transaction(function () use ($student, $data, $log) {
            $fields = array_intersect_key($data, array_flip(['name', 'phone', 'phone2', 'occupation', 'address']));

            $guardian = ($data['phone'] ?? '') !== ''
                ? Guardian::where('phone', $data['phone'])->where('name', $data['name'])->first()
                : null;
            $guardian ??= Guardian::create($fields);

            $makePrimary = (bool) ($data['is_primary'] ?? false) || ! $student->guardians()->wherePivot('is_primary', true)->exists();
            if ($makePrimary) {
                $student->guardians()->updateExistingPivot($student->guardians()->pluck('guardians.id')->all(), ['is_primary' => false]);
            }

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['relation' => $data['relation'], 'is_primary' => $makePrimary],
            ]);

            if ($log) {
                $this->logger->log('student.guardian_added', $student, $guardian->name.' ('.$data['relation'].')');
            }

            return $guardian;
        });
    }

    /** اطلاعات سرپرست برای همه‌ی دانش‌آموزان وابسته عوض می‌شود؛ نسبت و «اصلی» فقط برای همین دانش‌آموز. */
    public function updateGuardian(Student $student, Guardian $guardian, array $data): void
    {
        DB::transaction(function () use ($student, $guardian, $data) {
            $guardian->fill(array_intersect_key($data, array_flip(['name', 'phone', 'phone2', 'occupation', 'address'])));
            [$old, $new] = ModelChanges::capture($guardian);
            $guardian->save();

            $pivot = $student->guardians()->whereKey($guardian->id)->firstOrFail()->pivot;
            $wasPrimary = (bool) $pivot->is_primary;
            // «اصلی» با انتخاب سرپرست دیگر عوض می‌شود، نه با برداشتن تیک (هر دانش‌آموز همیشه یک سرپرست اصلی دارد)
            $isPrimary = $wasPrimary || (bool) ($data['is_primary'] ?? false);

            if ($isPrimary && ! $wasPrimary) {
                $student->guardians()->updateExistingPivot($student->guardians()->pluck('guardians.id')->all(), ['is_primary' => false]);
            }
            $student->guardians()->updateExistingPivot($guardian->id, ['relation' => $data['relation'], 'is_primary' => $isPrimary]);

            if ($pivot->relation !== $data['relation']) {
                [$old['relation'], $new['relation']] = [$pivot->relation, $data['relation']];
            }
            if ($isPrimary !== $wasPrimary) {
                [$old['is_primary'], $new['is_primary']] = [$wasPrimary, $isPrimary];
            }

            if ($new !== []) {
                $this->logger->log('student.guardian_updated', $student, $guardian->name, $old, $new);
            }
        });
    }

    /** جدا کردن سرپرست؛ اگر به هیچ دانش‌آموز دیگری وصل نبود، حذف می‌شود. */
    public function detachGuardian(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian) {
            $wasPrimary = (bool) $student->guardians()->whereKey($guardian->id)->firstOrFail()->pivot->is_primary;
            $student->guardians()->detach($guardian->id);

            if ($wasPrimary && ($next = $student->guardians()->first())) {
                $student->guardians()->updateExistingPivot($next->id, ['is_primary' => true]);
            }

            $this->logger->log('student.guardian_removed', $student, $guardian->name);

            if (! $guardian->students()->exists()) {
                $guardian->delete();
            }
        });
    }

    /** نمبر اساس بعدی: بزرگ‌ترین نمبر عددی + ۱ (پرونده‌های حذف‌شده هم حساب می‌شوند). */
    public function nextAdmissionNo(): string
    {
        $max = DB::table('students')
            ->whereRaw("admission_no REGEXP '^[0-9]+$'")
            ->lockForUpdate()
            ->max(DB::raw('CAST(admission_no AS UNSIGNED)'));

        return (string) (((int) $max) + 1);
    }
}
