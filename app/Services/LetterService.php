<?php

namespace App\Services;

use App\Enums\LetterDirection;
use App\Enums\LetterStatus;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterTemplate;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * مکاتیب رسمی (مرحله‌ی ۱۲). هر نوشتن داخل transaction با ثبت رویداد.
 * صادره: پیش‌نویس (بدون شماره، قابل ویرایش) ← صادر (شماره‌ی پشت‌سرهم سال شمسی تاریخ مکتوب) ← در صورت نیاز باطل.
 * وارده: هنگام ثبت شماره‌ی دفتر وارده می‌گیرد. هیچ مکتوبی حذف نمی‌شود و شماره‌ی باطل دوباره داده نمی‌شود.
 */
class LetterService
{
    /** جای‌نشان‌های قالب ← کلید برچسب در lang/fa/letters.php (placeholders) */
    public const PLACEHOLDERS = [
        'school_name', 'school_address', 'principal_name', 'today', 'letter_date', 'recipient', 'organization',
        'student_name', 'father_name', 'grandfather_name', 'admission_no', 'birth_date', 'class', 'academic_year',
        'staff_name', 'staff_father_name', 'staff_position', 'hire_date',
        'other_school', 'transfer_date', 'transfer_reason', 'certificate_no',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    // ---------- قالب و جای‌نشان ----------

    /** @return array<string, string> */
    public function context(?Student $student = null, ?Staff $staff = null, array $extra = []): array
    {
        $values = [
            'school_name' => (string) Setting::get('school_name'),
            'school_address' => collect([Setting::get('province'), Setting::get('district'), Setting::get('address')])->filter()->join('، '),
            'principal_name' => (string) Setting::get('principal_name'),
            'today' => JalaliDate::format(now(), 'long'),
        ];

        if ($student) {
            $enrollment = $student->currentEnrollment()->with(['section.gradeLevel', 'section.academicYear'])->first()
                ?? $student->enrollments()->with(['section.gradeLevel', 'section.academicYear'])->latest('id')->first();
            $values += [
                'student_name' => $student->full_name,
                'father_name' => (string) $student->father_name,
                'grandfather_name' => (string) $student->grandfather_name,
                'admission_no' => Digits::toPersian((string) $student->admission_no),
                'birth_date' => $student->birth_date ? JalaliDate::format($student->birth_date, 'long') : '',
                'class' => $enrollment?->section?->label ?? '',
                'academic_year' => $enrollment ? Digits::toPersian($enrollment->section->academicYear->name) : '',
                'certificate_no' => Digits::toPersian((string) $student->certificate_no),
            ];
        }
        if ($staff) {
            $values += [
                'staff_name' => $staff->full_name,
                'staff_father_name' => (string) $staff->father_name,
                'staff_position' => (string) ($staff->position ?: $staff->staff_type?->label()),
                'hire_date' => $staff->hire_date ? JalaliDate::format($staff->hire_date, 'long') : '',
            ];
        }

        return array_merge($values, array_filter($extra, fn ($v) => $v !== null));
    }

    /** {key} ← مقدار. جای‌نشانی که مقدار ندارد، همان‌طور می‌ماند تا کاربر ببیند و پر کند. */
    public function fill(?string $text, array $context): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $context) && $context[$m[1]] !== '' ? $context[$m[1]] : $m[0], (string) $text);
    }

    /** خانه‌های فرم مکتوب تازه از روی قالب (پیش از ذخیره؛ کاربر متن را ویرایش می‌کند) */
    public function draftFromTemplate(?LetterTemplate $template, ?Student $student, ?Staff $staff, array $extra = []): array
    {
        $context = $this->context($student, $staff, ['letter_date' => JalaliDate::format(now(), 'long')] + $extra);

        return [
            'type' => $template?->type?->value ?? 'general',
            'subject' => $this->fill($template?->subject, $context),
            'body' => $this->fill($template?->body, $context),
            'letter_template_id' => $template?->id,
            'student_id' => $student?->id,
            'staff_id' => $staff?->id,
        ];
    }

    // ---------- صادره ----------

    public function createOutgoing(array $data, User $user): Letter
    {
        return DB::transaction(function () use ($data, $user) {
            $letter = new Letter($data);
            $letter->direction = LetterDirection::Outgoing;
            $letter->forceFill(['status' => LetterStatus::Draft, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
            $this->logger->log('letter.created', $letter, $letter->subject, new: ['type' => $letter->type->label()], user: $user);

            return $letter;
        });
    }

    public function update(Letter $letter, array $data, User $user): Letter
    {
        if ($letter->isCancelled() || ($letter->isOutgoing() && ! $letter->isDraft())) {
            throw ValidationException::withMessages(['letter' => __('letters.locked')]);
        }

        return DB::transaction(function () use ($letter, $data, $user) {
            $letter->fill($data);
            $dirty = array_keys($letter->getDirty());
            $old = array_intersect_key($letter->getOriginal(), array_flip($dirty));
            $letter->forceFill(['updated_by' => $user->id])->save();
            if ($dirty !== []) {
                $this->logger->log('letter.updated', $letter, $this->label($letter),
                    $this->plain($old), $this->plain(array_intersect_key($letter->getAttributes(), array_flip($dirty))), $user);
            }

            return $letter;
        });
    }

    /** صدور: شماره‌ی پشت‌سرهم و قفل متن */
    public function issue(Letter $letter, User $user): Letter
    {
        return DB::transaction(function () use ($letter, $user) {
            $letter = Letter::query()->lockForUpdate()->findOrFail($letter->id);
            if (! $letter->isOutgoing() || ! $letter->isDraft()) {
                throw ValidationException::withMessages(['letter' => __('letters.not_draft')]);
            }
            if (trim((string) $letter->body) === '') {
                throw ValidationException::withMessages(['letter' => __('letters.empty_body')]);
            }
            if (preg_match('/\{[a-z_]+\}/', $letter->subject.' '.$letter->body, $m)) {
                throw ValidationException::withMessages(['letter' => __('letters.unfilled_placeholder', ['placeholder' => $m[0]])]);
            }

            $this->assignNumber($letter);
            $letter->forceFill(['status' => LetterStatus::Issued, 'issued_at' => now(), 'issued_by' => $user->id, 'updated_by' => $user->id])->save();
            $this->logger->log('letter.issued', $letter, $this->label($letter), new: ['number' => $letter->number], user: $user);

            return $letter;
        });
    }

    public function cancel(Letter $letter, string $reason, User $user): Letter
    {
        return DB::transaction(function () use ($letter, $reason, $user) {
            $letter = Letter::query()->lockForUpdate()->findOrFail($letter->id);
            if ($letter->isCancelled()) {
                throw ValidationException::withMessages(['letter' => __('letters.already_cancelled')]);
            }
            $old = $letter->status->label();
            $letter->forceFill(['status' => LetterStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => $reason])->save();
            $this->logger->log('letter.cancelled', $letter, $this->label($letter).' — '.$reason, ['status' => $old], ['status' => $letter->status->label()], $user);

            return $letter;
        });
    }

    // ---------- وارده ----------

    public function registerIncoming(array $data, User $user, ?UploadedFile $scan = null): Letter
    {
        $path = $scan ? PrivateFiles::store($scan, 'letters') : null;

        try {
            return DB::transaction(function () use ($data, $user, $scan, $path) {
                $letter = new Letter($data);
                $letter->direction = LetterDirection::Incoming;
                $letter->forceFill(['status' => LetterStatus::Issued, 'issued_at' => now(), 'issued_by' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id]);
                $this->assignNumber($letter);
                $letter->save();
                if ($scan) {
                    $this->storeAttachment($letter, $scan, $path, __('letters.scan'), $user);
                }
                $this->logger->log('letter.registered', $letter, $this->label($letter), new: ['number' => $letter->number, 'organization' => (string) $letter->organization], user: $user);

                return $letter;
            });
        } catch (Throwable $e) {
            PrivateFiles::delete($path);
            throw $e;
        }
    }

    // ---------- ضمیمه ----------

    public function addAttachment(Letter $letter, UploadedFile $file, string $title, User $user): LetterAttachment
    {
        if ($letter->isCancelled()) {
            throw ValidationException::withMessages(['file' => __('letters.locked')]);
        }
        $path = PrivateFiles::store($file, 'letters');
        try {
            return DB::transaction(function () use ($letter, $file, $path, $title, $user) {
                $attachment = $this->storeAttachment($letter, $file, $path, $title, $user);
                $this->logger->log('letter.attachment_added', $letter, $this->label($letter).' — '.$title, user: $user);

                return $attachment;
            });
        } catch (Throwable $e) {
            PrivateFiles::delete($path);
            throw $e;
        }
    }

    /** ضمیمه فقط پیش از صدور حذف می‌شود؛ بعد از آن جزو بایگانی است */
    public function removeAttachment(Letter $letter, LetterAttachment $attachment, User $user): void
    {
        if (! $letter->isDraft()) {
            throw ValidationException::withMessages(['file' => __('letters.attachment_locked')]);
        }
        DB::transaction(function () use ($letter, $attachment, $user) {
            $attachment->delete();
            $this->logger->log('letter.attachment_removed', $letter, $this->label($letter).' — '.$attachment->title,
                old: ['title' => $attachment->title, 'file' => $attachment->original_name], user: $user);
        });
        PrivateFiles::delete($attachment->file_path);
    }

    // ---------- قالب‌ها ----------

    public function saveTemplate(?LetterTemplate $template, array $data, User $user): LetterTemplate
    {
        return DB::transaction(function () use ($template, $data, $user) {
            $template ??= new LetterTemplate;
            $creating = ! $template->exists;
            $template->fill($data)->save();
            $this->logger->log($creating ? 'letter_template.created' : 'letter_template.updated', $template, $template->name, user: $user);

            return $template;
        });
    }

    // ---------- کمک ----------

    public static function prefix(LetterDirection $direction): string
    {
        return trim((string) Setting::get($direction === LetterDirection::Outgoing ? 'letter_outgoing_prefix' : 'letter_incoming_prefix'));
    }

    private function assignNumber(Letter $letter): void
    {
        $year = JalaliDate::fromGregorian($letter->letter_date)[0];
        $last = Letter::query()->where('direction', $letter->direction)->where('year', $year)->lockForUpdate()->max('serial');
        $serial = (int) $last + 1;
        $letter->forceFill([
            'year' => $year,
            'serial' => $serial,
            'number' => self::prefix($letter->direction).$year.'/'.str_pad((string) $serial, 3, '0', STR_PAD_LEFT),
        ]);
    }

    private function storeAttachment(Letter $letter, UploadedFile $file, string $path, string $title, User $user): LetterAttachment
    {
        return $letter->attachments()->create([
            'title' => $title,
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    private function label(Letter $letter): string
    {
        return ($letter->number ? Digits::toPersian($letter->number).' — ' : '').$letter->subject;
    }

    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : (is_bool($v) ? ($v ? '1' : '0') : (string) $v), $values);
    }
}
