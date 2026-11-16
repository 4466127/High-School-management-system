<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use App\Models\Enrollment;
use App\Models\ReexamMark;
use App\Models\Section;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * امتحان مشروطی (دوباره): دانش‌آموزان «مشروط» بخشِ نهایی‌شده در مضامین ناکام دوباره امتحان می‌دهند.
 * نمره‌ی مشروطی از ۱۰۰ و جای نمره‌ی نهایی آن مضمون را می‌گیرد (ResultCalculator::reexamResult).
 * نهایی‌کردن مشروطی نتیجه را «کامیاب» یا «ناکام» می‌کند؛ نتیجه‌ی اصلی در final_snapshot.reexam.original می‌ماند
 * تا لغو آن (پیش از ارتقا) همه‌چیز را برگرداند.
 */
class ReexamService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * ورق مشروطی یک بخش.
     *
     * @return Collection<int, array{enrollment: Enrollment, subjects: list<array{id: int, name: string, final: ?float, mark: ?ReexamMark}>, finalized: bool, outcome: array}>
     */
    public function sheet(Section $section): Collection
    {
        $enrollments = Enrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->whereNotNull('finalized_at')
            ->where(fn ($q) => $q->where('final_result', FinalResult::Conditional)->orWhereNotNull('reexam_finalized_at'))
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->orderBy('id')
            ->get();

        $marks = ReexamMark::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id')->all() ?: [0])
            ->get()
            ->groupBy('enrollment_id')
            ->map(fn ($group) => $group->keyBy('subject_id'));

        return $enrollments->map(function (Enrollment $enrollment) use ($marks) {
            $snapshot = $enrollment->final_snapshot ?? [];
            $finalized = $enrollment->reexam_finalized_at !== null;
            $reexamIds = $finalized ? array_map('intval', array_keys($snapshot['reexam']['subjects'] ?? [])) : null;
            $own = $marks->get($enrollment->id, collect());

            $subjects = collect($snapshot['subjects'] ?? [])
                ->filter(fn ($s) => $finalized ? in_array((int) $s['id'], $reexamIds, true) : $s['passed'] === false)
                ->map(fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'final' => $s['final'], 'mark' => $own->get($s['id'])])
                ->values()
                ->all();

            return [
                'enrollment' => $enrollment,
                'subjects' => $subjects,
                'finalized' => $finalized,
                'outcome' => $finalized
                    ? ['result' => $enrollment->final_result, 'average' => $snapshot['average'] ?? null, 'grade' => $snapshot['grade'] ?? null, 'incomplete' => false]
                    : $this->outcome($enrollment, $own),
            ];
        })->values();
    }

    /**
     * ذخیره‌ی نمره‌های مشروطی (فقط مضامین ناکام دانش‌آموزانی که مشروطی‌شان نهایی نشده).
     *
     * @param  array<int|string, array<int|string, ?string>>  $marks  enrollment_id => subject_id => نمره
     * @param  array<int|string, array<int|string, mixed>>  $absent
     * @return array{entered: int, changed: int}
     */
    public function save(Section $section, array $marks, array $absent, User $user): array
    {
        $rows = $this->sheet($section)->reject(fn ($row) => $row['finalized']);

        $errors = [];
        foreach ($rows as $row) {
            $id = $row['enrollment']->id;
            foreach ($row['subjects'] as $subject) {
                $raw = trim(Digits::toLatin((string) ($marks[$id][$subject['id']] ?? '')));
                if ($raw !== '' && empty($absent[$id][$subject['id']]) && (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100)) {
                    $errors["marks.$id.{$subject['id']}"] = __('exams.reexam_mark_invalid');
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($section, $rows, $marks, $absent, $user) {
            $entered = 0;
            $old = [];
            $new = [];

            foreach ($rows as $row) {
                $enrollment = $row['enrollment'];
                foreach ($row['subjects'] as $subject) {
                    $isAbsent = ! empty($absent[$enrollment->id][$subject['id']]);
                    $raw = trim(Digits::toLatin((string) ($marks[$enrollment->id][$subject['id']] ?? '')));
                    $value = $isAbsent || $raw === '' ? null : round((float) $raw, 2);
                    $mark = ReexamMark::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $subject['id'])->lockForUpdate()->first();
                    $who = Digits::toPersian((string) $enrollment->roll_no).' — '.$enrollment->student->full_name.' — '.$subject['name'];

                    if (! $mark) {
                        if ($value === null && ! $isAbsent) {
                            continue;
                        }
                        ReexamMark::create([
                            'enrollment_id' => $enrollment->id, 'subject_id' => $subject['id'],
                            'mark' => $value, 'is_absent' => $isAbsent, 'entered_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $entered++;

                        continue;
                    }

                    $before = $mark->mark === null ? null : (float) $mark->mark;
                    if ($before !== $value || $mark->is_absent !== $isAbsent) {
                        $old[$who] = $this->describe($before, $mark->is_absent);
                        $new[$who] = $this->describe($value, $isAbsent);
                        $mark->update(['mark' => $value, 'is_absent' => $isAbsent, 'updated_by' => $user->id]);
                    }
                }
            }

            $section->loadMissing('gradeLevel');
            if ($entered > 0 || $new !== []) {
                $this->logger->log('reexam.marks_saved', $section, $section->label, $old, $new + ['count' => $entered], $user);
            }

            return ['entered' => $entered, 'changed' => count($new)];
        });
    }

    /** @return array<string, int> نتیجه ← تعداد */
    public function finalize(Section $section, User $user): array
    {
        $rows = $this->sheet($section)->reject(fn ($row) => $row['finalized']);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['reexam' => __('exams.reexam_nothing_to_finalize')]);
        }
        $incomplete = $rows->filter(fn ($row) => $row['outcome']['incomplete'])->count();
        if ($incomplete > 0) {
            throw ValidationException::withMessages(['reexam' => __('exams.reexam_incomplete', ['count' => Digits::toPersian((string) $incomplete)])]);
        }

        return DB::transaction(function () use ($section, $rows, $user) {
            $counts = [];
            foreach ($rows as $row) {
                $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($row['enrollment']->id);
                $snapshot = $enrollment->final_snapshot;
                $outcome = $row['outcome'];

                $snapshot['reexam'] = [
                    'original' => [
                        'result' => $snapshot['result'], 'total' => $snapshot['total'], 'average' => $snapshot['average'],
                        'failed' => $snapshot['failed'], 'grade' => $snapshot['grade'] ?? null,
                    ],
                    'subjects' => collect($row['subjects'])->mapWithKeys(fn ($s) => [$s['id'] => [
                        'mark' => $s['mark']->mark === null ? null : (float) $s['mark']->mark, 'absent' => $s['mark']->is_absent,
                    ]])->all(),
                    'finalized_on' => now()->toDateString(),
                ];
                foreach ($snapshot['subjects'] as $i => $subject) {
                    if (isset($outcome['subjects'][$subject['id']]) && $outcome['subjects'][$subject['id']]['reexam'] !== null) {
                        $snapshot['subjects'][$i]['reexam'] = $outcome['subjects'][$subject['id']]['reexam'];
                        $snapshot['subjects'][$i]['passed'] = $outcome['subjects'][$subject['id']]['passed'];
                    }
                }
                $snapshot['total'] = $outcome['total'];
                $snapshot['average'] = $outcome['average'];
                $snapshot['failed'] = $outcome['failed'];
                $snapshot['result'] = $outcome['result']->value;
                $snapshot['grade'] = $outcome['grade'];

                $enrollment->forceFill([
                    'final_result' => $outcome['result'],
                    'final_total' => $outcome['total'],
                    'final_average' => $outcome['average'],
                    'final_grade' => $outcome['grade'],
                    'reexam_finalized_at' => now(),
                    'reexam_finalized_by' => $user->id,
                    'final_snapshot' => $snapshot,
                ])->save();

                $label = $outcome['result']->label();
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }

            $section->loadMissing('gradeLevel');
            $this->logger->log('reexam.finalized', $section, $section->label, new: $counts, user: $user);

            return $counts;
        });
    }

    /** لغو نتیجه‌ی مشروطی (فقط پیش از ارتقا یا تکرار صنف): نتیجه دوباره «مشروط» می‌شود؛ نمره‌های مشروطی می‌مانند */
    public function unfinalize(Section $section, User $user, string $reason): int
    {
        return DB::transaction(function () use ($section, $user, $reason) {
            $enrollments = Enrollment::query()->where('section_id', $section->id)->whereNotNull('reexam_finalized_at')->lockForUpdate()->get();
            if ($enrollments->contains(fn (Enrollment $e) => $e->status !== EnrollmentStatus::Active)) {
                throw ValidationException::withMessages(['reexam' => __('exams.reexam_already_promoted')]);
            }

            foreach ($enrollments as $enrollment) {
                $snapshot = $enrollment->final_snapshot;
                $original = $snapshot['reexam']['original'];
                $reexamIds = array_map('intval', array_keys($snapshot['reexam']['subjects'] ?? []));

                foreach ($snapshot['subjects'] as $i => $subject) {
                    if (in_array((int) $subject['id'], $reexamIds, true)) {
                        unset($snapshot['subjects'][$i]['reexam']);
                        $snapshot['subjects'][$i]['passed'] = false;
                    }
                }
                foreach (['result', 'total', 'average', 'failed', 'grade'] as $key) {
                    $snapshot[$key] = $original[$key];
                }
                unset($snapshot['reexam']);

                $enrollment->forceFill([
                    'final_result' => FinalResult::from($original['result']),
                    'final_total' => $original['total'],
                    'final_average' => $original['average'],
                    'final_grade' => $original['grade'],
                    'reexam_finalized_at' => null,
                    'reexam_finalized_by' => null,
                    'final_snapshot' => $snapshot,
                ])->save();
            }

            $section->loadMissing('gradeLevel');
            $this->logger->log('reexam.unfinalized', $section, $section->label.' — '.$reason, new: ['count' => $enrollments->count()], user: $user);

            return $enrollments->count();
        });
    }

    public function hasFinalized(Section $section): bool
    {
        return Enrollment::where('section_id', $section->id)->whereNotNull('reexam_finalized_at')->exists();
    }

    /** نتیجه‌ی پیش‌بینی‌شده با نمره‌های مشروطی فعلی (با قاعده‌های ذخیره‌شده هنگام نهایی‌کردن) */
    private function outcome(Enrollment $enrollment, Collection $marks): array
    {
        $snapshot = $enrollment->final_snapshot ?? [];
        $input = $marks->map(fn (ReexamMark $m) => ['mark' => $m->mark === null ? null : (float) $m->mark, 'absent' => $m->is_absent])->all();

        return ResultCalculator::fromSnapshot($snapshot['settings'] ?? [])->reexamResult($snapshot['subjects'] ?? [], $input);
    }

    private function describe(?float $mark, bool $absent): string
    {
        return $absent ? __('exams.absent') : ($mark === null ? '—' : Digits::toPersian(MarkService::format($mark)));
    }
}
