<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Staff;
use App\Models\StaffWorkHistory;
use App\Support\ModelChanges;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StaffService
{
    private const PHOTO_DIR = 'staff/photos';

    public function __construct(private readonly ActivityLogger $logger) {}

    public function create(array $data, ?UploadedFile $photo = null): Staff
    {
        $storedPhoto = $photo ? PrivateFiles::store($photo, self::PHOTO_DIR) : null;

        try {
            return DB::transaction(function () use ($data, $storedPhoto) {
                $staff = new Staff($data);
                $staff->employment_status = EmploymentStatus::Active;
                $staff->photo_path = $storedPhoto;
                $staff->save();

                $this->logger->log('staff.created', $staff, $staff->full_name.' — '.$staff->staff_type->label());

                return $staff;
            });
        } catch (\Throwable $e) {
            PrivateFiles::delete($storedPhoto);
            throw $e;
        }
    }

    public function update(Staff $staff, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Staff
    {
        $oldPhoto = $staff->photo_path;
        $newPhoto = $photo ? PrivateFiles::store($photo, self::PHOTO_DIR) : null;

        DB::transaction(function () use ($staff, $data, $newPhoto, $removePhoto) {
            $staff->fill($data);
            if ($newPhoto) {
                $staff->photo_path = $newPhoto;
            } elseif ($removePhoto) {
                $staff->photo_path = null;
            }

            [$old, $new] = ModelChanges::capture($staff);
            $staff->save();

            if ($new !== []) {
                $this->logger->log('staff.updated', $staff, $staff->full_name, $old, $new);
            }
        });

        if ($oldPhoto && $oldPhoto !== $staff->photo_path) {
            PrivateFiles::delete($oldPhoto);
        }

        return $staff;
    }

    public function changeStatus(Staff $staff, EmploymentStatus $status, ?string $reason, ?string $date): void
    {
        DB::transaction(function () use ($staff, $status, $reason, $date) {
            $old = ['employment_status' => $staff->employment_status->value, 'status_reason' => $staff->status_reason];

            $staff->employment_status = $status;
            $staff->status_reason = $reason;
            $staff->status_changed_on = $date ?? now()->toDateString();
            $staff->save();

            // کارمندی که رفته، دیگر نباید وارد سیستم شود
            if ($status === EmploymentStatus::Left && $staff->user && $staff->user->is_active) {
                $staff->user->update(['is_active' => false]);
                DB::table('sessions')->where('user_id', $staff->user->id)->delete();
                $this->logger->log('user.deactivated', $staff->user, $staff->user->name);
            }

            $this->logger->log('staff.status_changed', $staff, $staff->full_name.($reason ? ' — '.$reason : ''),
                $old, ['employment_status' => $status->value, 'status_reason' => $reason]);
        });
    }

    public function delete(Staff $staff, string $reason): void
    {
        DB::transaction(function () use ($staff, $reason) {
            $staff->delete();
            $this->logger->log('staff.deleted', $staff, $staff->full_name.' — '.$reason);
        });
    }

    public function restore(Staff $staff): void
    {
        DB::transaction(function () use ($staff) {
            $staff->restore();
            $this->logger->log('staff.restored', $staff, $staff->full_name);
        });
    }

    public function addWorkHistory(Staff $staff, array $data): StaffWorkHistory
    {
        return DB::transaction(function () use ($staff, $data) {
            $entry = $staff->workHistory()->create($data);
            $this->logger->log('staff.work_history_added', $staff, $staff->full_name.' — '.$entry->title);

            return $entry;
        });
    }

    public function removeWorkHistory(Staff $staff, StaffWorkHistory $entry): void
    {
        DB::transaction(function () use ($staff, $entry) {
            $entry->delete();
            $this->logger->log('staff.work_history_removed', $staff, $staff->full_name.' — '.$entry->title,
                old: ['title' => $entry->title, 'organization' => $entry->organization]);
        });
    }
}
