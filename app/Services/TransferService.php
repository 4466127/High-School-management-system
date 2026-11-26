<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Enums\TransferDirection;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentTransfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * انتقال میان مکاتب (مرحله‌ی ۱۳) — جدا از ارتقای صنف در همین مکتب (EnrollmentService::promote).
 * صادره: شمولیت فعال سال جاری «منتقل» بسته می‌شود (از راه EnrollmentService::leave)، وضعیت دانش‌آموز «منتقل‌شده».
 * وارده: سابقه‌ی مکتب قبلی (و فایل سوابق اگر هست) به پرونده‌ی دانش‌آموز وصل می‌شود؛ شمولیت از راه عادی.
 * هیچ سطری از سوابق تحصیلی پاک یا بازنویسی نمی‌شود.
 */
class TransferService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly EnrollmentService $enrollments,
        private readonly StudentRecordService $records,
    ) {}

    /**
     * @param  array<string, mixed>  $data  خانه‌های فرم + clearance_confirmed
     */
    public function transferOut(Student $student, array $data, User $user): StudentTransfer
    {
        if ($student->trashed() || ! in_array($student->status, [StudentStatus::Active, StudentStatus::Inactive], true)) {
            throw ValidationException::withMessages(['other_school' => __('transfers.cannot_transfer', ['status' => $student->status->label()])]);
        }
        $outstanding = $this->records->outstandingCents($student);
        if ($outstanding > 0 && empty($data['clearance_confirmed'])) {
            throw ValidationException::withMessages(['clearance_confirmed' => __('transfers.clearance_required', ['amount' => StudentRecordService::money($outstanding)])]);
        }

        return DB::transaction(function () use ($student, $data, $user, $outstanding) {
            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', AcademicYear::current()?->id ?? 0)
                ->where('status', EnrollmentStatus::Active)
                ->lockForUpdate()
                ->first();

            $reason = __('transfers.leave_reason', ['school' => $data['other_school']]).' — '.$data['reason'];
            if ($enrollment) {
                $this->enrollments->leave($enrollment, EnrollmentStatus::Transferred, $data['transfer_date'], mb_substr($reason, 0, 500));
            } else {
                $student->forceFill(['status' => StudentStatus::Transferred, 'status_reason' => mb_substr($reason, 0, 500), 'status_changed_on' => $data['transfer_date']])->save();
            }

            $transfer = new StudentTransfer($data);
            $transfer->forceFill([
                'student_id' => $student->id,
                'direction' => TransferDirection::Out,
                'status' => 'completed',
                'enrollment_id' => $enrollment?->id,
                'outstanding_balance' => Money::fromCents($outstanding),
                'created_by' => $user->id,
            ])->save();

            $this->logger->log('student.transfer_out', $student, $student->full_name.' ← '.$transfer->other_school, new: [
                'school' => $transfer->other_school, 'date' => $transfer->transfer_date->toDateString(),
                'outstanding' => Money::format($transfer->outstanding_balance),
            ], user: $user);

            return $transfer;
        });
    }

    /** @param  ?array  $package  فایل سوابق بازشده (StudentRecordService::parsePackage) */
    public function transferIn(Student $student, array $data, User $user, ?array $package = null): StudentTransfer
    {
        return DB::transaction(function () use ($student, $data, $user, $package) {
            $transfer = new StudentTransfer($data);
            $transfer->forceFill([
                'student_id' => $student->id,
                'direction' => TransferDirection::In,
                'status' => 'completed',
                'previous_records' => $package,
                'created_by' => $user->id,
            ])->save();

            $this->logger->log('student.transfer_in', $student, $student->full_name.' → '.$transfer->other_school, new: [
                'school' => $transfer->other_school, 'date' => $transfer->transfer_date->toDateString(), 'records' => $package ? '✓' : '',
            ], user: $user);

            return $transfer;
        });
    }

    /**
     * باطل کردن انتقال (اشتباه ثبت شده بود). صادره: اگر شمولیت همان سال جاری بود، دوباره فعال می‌شود.
     */
    public function cancel(StudentTransfer $transfer, string $reason, User $user): void
    {
        if ($transfer->isCancelled()) {
            throw ValidationException::withMessages(['reason' => __('transfers.already_cancelled')]);
        }

        DB::transaction(function () use ($transfer, $reason, $user) {
            $student = $transfer->student;
            if ($transfer->isOut() && $student->status === StudentStatus::Transferred) {
                $enrollment = $transfer->enrollment;
                if ($enrollment && $enrollment->status === EnrollmentStatus::Transferred && $enrollment->academic_year_id === AcademicYear::current()?->id) {
                    $this->enrollments->enroll($student, $enrollment->section, $enrollment->enrolled_on?->toDateString());
                } else {
                    $student->forceFill(['status' => StudentStatus::Active, 'status_reason' => null, 'status_changed_on' => now()->toDateString()])->save();
                }
            }

            $transfer->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => $reason])->save();
            $this->logger->log('student.transfer_cancelled', $student, $student->full_name.' — '.$reason, ['school' => $transfer->other_school], ['status' => 'cancelled'], $user);
        });
    }

    /** مشخصات سند فراغت (فقط برای فارغ‌ها) */
    public function saveCertificate(Student $student, array $data, User $user): void
    {
        if ($student->status !== StudentStatus::Graduated) {
            throw ValidationException::withMessages(['certificate_no' => __('archive.not_graduated')]);
        }
        DB::transaction(function () use ($student, $data, $user) {
            $old = ['certificate_no' => (string) $student->certificate_no, 'certificate_date' => (string) $student->certificate_date?->toDateString()];
            $student->forceFill([
                'certificate_no' => $data['certificate_no'] ?? null,
                'certificate_date' => $data['certificate_date'] ?? null,
                'certificate_note' => $data['certificate_note'] ?? null,
            ])->save();
            $this->logger->log('student.certificate_saved', $student, $student->full_name, $old,
                ['certificate_no' => (string) $student->certificate_no, 'certificate_date' => (string) $student->certificate_date?->toDateString()], $user);
        });
    }
}
