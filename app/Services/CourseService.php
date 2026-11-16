<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ParticipantStatus;
use App\Models\Course;
use App\Models\CourseAttendance;
use App\Models\CourseGroup;
use App\Models\CourseParticipant;
use App\Models\CourseSession;
use App\Models\Student;
use App\Models\User;
use App\Support\JalaliDate;
use App\Support\ModelChanges;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** کورس‌ها، گروه‌ها، اشتراک‌کنندگان و حاضری کورس. هر نوشتن داخل transaction و با ثبت رویداد. */
class CourseService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function saveCourse(?Course $course, array $data, User $user): Course
    {
        return DB::transaction(function () use ($course, $data, $user) {
            if (! $course) {
                $course = Course::create($data + ['created_by' => $user->id]);
                $this->logger->log('course.created', $course, $course->name, user: $user);

                return $course;
            }

            $course->fill($data);
            [$old, $new] = ModelChanges::capture($course);
            $course->save();
            if ($new !== []) {
                $this->logger->log('course.updated', $course, $course->name, $old, $new, $user);
            }

            return $course;
        });
    }

    public function saveGroup(Course $course, ?CourseGroup $group, array $data, User $user): CourseGroup
    {
        return DB::transaction(function () use ($course, $group, $data, $user) {
            $group ??= new CourseGroup(['course_id' => $course->id]);
            $created = ! $group->exists;
            $group->fill($data);
            [$old, $new] = ModelChanges::capture($group);
            $group->save();
            $group->setRelation('course', $course);

            if ($created) {
                $this->logger->log('course.group_created', $course, $group->label, user: $user);
            } elseif ($new !== []) {
                $this->logger->log('course.group_updated', $course, $group->label, $old, $new, $user);
            }

            return $group;
        });
    }

    /**
     * شامل کردن دانش‌آموزان مکتب در یک گروه. کسی که در گروه دیگری از همین کورس است، دوباره شامل نمی‌شود.
     *
     * @param  list<int>  $studentIds
     * @return array{added: int, skipped: int}
     */
    public function addStudents(CourseGroup $group, array $studentIds, string $joinedOn, User $user): array
    {
        return DB::transaction(function () use ($group, $studentIds, $joinedOn, $user) {
            $group->loadMissing('course');
            $already = CourseParticipant::query()
                ->whereIn('course_group_id', $group->course->groups()->pluck('id'))
                ->whereIn('student_id', $studentIds ?: [0])
                ->lockForUpdate()
                ->pluck('student_id')
                ->all();

            $added = [];
            foreach (Student::whereIn('id', $studentIds ?: [0])->get() as $student) {
                if (in_array($student->id, $already, true)) {
                    continue;
                }
                CourseParticipant::create([
                    'course_group_id' => $group->id, 'student_id' => $student->id, 'gender' => $student->gender,
                    'joined_on' => $joinedOn, 'status' => ParticipantStatus::Active,
                ]);
                $added[] = $student->full_name;
            }

            if ($added !== []) {
                $this->logger->log('course.participants_added', $group->course, $group->label, new: ['names' => implode('، ', $added)], user: $user);
            }

            return ['added' => count($added), 'skipped' => count($studentIds) - count($added)];
        });
    }

    public function addExternal(CourseGroup $group, array $data, User $user): CourseParticipant
    {
        return DB::transaction(function () use ($group, $data, $user) {
            $participant = CourseParticipant::create($data + ['course_group_id' => $group->id, 'status' => ParticipantStatus::Active]);
            $group->loadMissing('course');
            $this->logger->log('course.participants_added', $group->course, $group->label, new: ['names' => $participant->name], user: $user);

            return $participant;
        });
    }

    public function updateParticipant(CourseParticipant $participant, array $data, User $user): void
    {
        DB::transaction(function () use ($participant, $data, $user) {
            $participant->fill($data);
            [$old, $new] = ModelChanges::capture($participant);
            $participant->save();
            if ($new !== []) {
                $participant->load('group.course');
                $this->logger->log('course.participant_updated', $participant->group->course, $participant->display_name, $old, $new, $user);
            }
        });
    }

    // ------------------------------------------------------------------ حاضری کورس

    /**
     * اشتراک‌کنندگان ورق حاضری: مشغولان گروه + کسانی که در همین روز قبلاً ثبت شده‌اند (حتی اگر بعد رفته باشند).
     *
     * @return Collection<int, CourseParticipant> participant_id => participant
     */
    public function sheetParticipants(CourseGroup $group, ?CourseSession $session): Collection
    {
        $recorded = $session ? $session->records()->pluck('course_participant_id')->all() : [];

        return CourseParticipant::query()
            ->with('student')
            ->where('course_group_id', $group->id)
            ->where(fn ($q) => $q->where('status', ParticipantStatus::Active)->orWhereIn('id', $recorded ?: [0]))
            ->orderBy('id')
            ->get()
            ->sortBy(fn (CourseParticipant $p) => $p->display_name)
            ->keyBy('id');
    }

    /**
     * @param  array<int|string, string>  $statuses  participant_id => وضعیت
     * @param  array<int|string, ?string>  $notes
     * @return array{created: bool, changed: int}
     */
    public function saveAttendance(CourseGroup $group, DateTimeInterface $date, array $statuses, array $notes, User $user): array
    {
        $day = $date->format('Y-m-d');

        return DB::transaction(function () use ($group, $date, $day, $statuses, $notes, $user) {
            $session = CourseSession::createOrFirst(['course_group_id' => $group->id, 'date' => $day], ['taken_by' => $user->id]);
            $created = $session->wasRecentlyCreated;
            $session = CourseSession::whereKey($session->id)->lockForUpdate()->first();
            $records = $session->records()->get()->keyBy('course_participant_id');
            $participants = $this->sheetParticipants($group, $session);

            if ($participants->isEmpty() || $participants->keys()->contains(fn ($id) => ! isset($statuses[$id]) || ! AttendanceStatus::tryFrom((string) $statuses[$id]))) {
                throw ValidationException::withMessages(['status' => __('attendance.list_changed')]);
            }

            $old = [];
            $new = [];
            foreach ($participants as $id => $participant) {
                $status = AttendanceStatus::from($statuses[$id]);
                $note = trim((string) ($notes[$id] ?? '')) ?: null;
                $record = $records->get($id);

                if (! $record) {
                    CourseAttendance::create(['course_session_id' => $session->id, 'course_participant_id' => $id, 'status' => $status, 'note' => $note]);

                    continue;
                }
                if ($record->status !== $status || $record->note !== $note) {
                    $old[$participant->display_name] = $record->status->label().($record->note ? ' ('.$record->note.')' : '');
                    $new[$participant->display_name] = $status->label().($note ? ' ('.$note.')' : '');
                    $record->update(['status' => $status, 'note' => $note]);
                }
            }

            $group->loadMissing('course');
            $description = $group->label.' — '.JalaliDate::format($date);
            if ($created) {
                $this->logger->log('course.attendance_taken', $group->course, $description, new: ['count' => $participants->count()], user: $user);
            } elseif ($new !== []) {
                $session->update(['updated_by' => $user->id]);
                $this->logger->log('course.attendance_corrected', $group->course, $description, $old, $new, $user);
            }

            return ['created' => $created, 'changed' => count($new)];
        });
    }

    /**
     * شمار حاضری هر اشتراک‌کننده‌ی یک کورس.
     *
     * @return Collection<int, object{present:int, late:int, absent:int, excused:int, sick:int, total:int}> participant_id => شمار
     */
    public function attendanceTotals(Course $course): Collection
    {
        return CourseAttendance::query()
            ->join('course_participants', 'course_participants.id', '=', 'course_attendance.course_participant_id')
            ->whereIn('course_participants.course_group_id', $course->groups()->pluck('id')->all() ?: [0])
            ->groupBy('course_attendance.course_participant_id')
            ->selectRaw("course_attendance.course_participant_id as id,
                SUM(course_attendance.status = 'present') as present, SUM(course_attendance.status = 'late') as late,
                SUM(course_attendance.status = 'absent') as absent, SUM(course_attendance.status = 'excused') as excused,
                SUM(course_attendance.status = 'sick') as sick, COUNT(*) as total")
            ->get()
            ->keyBy('id');
    }
}
