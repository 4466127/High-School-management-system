<?php

namespace App\Http\Controllers\Courses;

use App\Enums\Gender;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentRequest;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseParticipant;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\CourseService;
use App\Services\FinanceService;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** اشتراک‌کنندگان کورس: شامل کردن دانش‌آموزان مکتب، ثبت فرد بیرونی، تغییر گروه و وضعیت (مجوز: courses.manage). */
class ParticipantController extends Controller
{
    public function __construct(private readonly CourseService $courses) {}

    public function storeStudents(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $data = $request->validate([
            'course_group_id' => ['required', 'integer', Rule::exists('course_groups', 'id')->where('course_id', $course->id)],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'joined_on' => ['required', new ValidJalaliDate],
        ], ['student_ids.required' => __('courses.pick_students')], __('courses.attributes'));

        $done = $this->courses->addStudents(
            CourseGroup::findOrFail($data['course_group_id']),
            array_map('intval', $data['student_ids']),
            JalaliDate::parse($data['joined_on'])->toDateString(),
            $request->user(),
        );

        return redirect()->to(route('courses.list.show', $course).'#participants')->with('success', __('courses.students_added', [
            'added' => Digits::toPersian((string) $done['added']), 'skipped' => Digits::toPersian((string) $done['skipped']),
        ]));
    }

    public function storeExternal(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $data = $request->validate([
            'course_group_id' => ['required', 'integer', Rule::exists('course_groups', 'id')->where('course_id', $course->id)],
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'joined_on' => ['required', new ValidJalaliDate],
            'note' => ['nullable', 'string', 'max:255'],
            'fee' => ['nullable', new ValidAmount],
        ], ['phone.regex' => __('validation.phone')], __('courses.attributes'));
        $data['joined_on'] = JalaliDate::parse($data['joined_on'])->toDateString();
        // فیس اختیاری ← سطر «فیس اشتراک» در صندوق همین کورس (فقط برای کسی که دفتر مالی کورس را دارد)
        $fee = filled($data['fee'] ?? null) ? FinanceService::parseAmount($data['fee']) : 0;
        unset($data['fee']);
        if ($fee > 0 && ! Gate::allows('ledger', Course::class)) {
            throw ValidationException::withMessages(['fee' => __('courses.fee_no_permission')]);
        }

        $this->courses->addExternal(CourseGroup::findOrFail($data['course_group_id']), $data, $request->user(), $fee);

        return redirect()->to(route('courses.list.show', $course).'#participants')->with('success', __('courses.external_added', ['name' => $data['name']])
            .($fee > 0 ? ' '.__('courses.fee_recorded', ['amount' => Money::format(Money::fromCents($fee))]) : ''));
    }

    public function update(Request $request, CourseParticipant $participant): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $courseId = $participant->group->course_id;
        $data = $request->validate([
            'course_group_id' => ['required', 'integer', Rule::exists('course_groups', 'id')->where('course_id', $courseId)],
            'status' => ['required', Rule::enum(ParticipantStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], __('courses.attributes'));

        $this->courses->updateParticipant($participant, $data, $request->user());

        return redirect()->to(route('courses.list.show', $courseId).'#participants')->with('success', __('courses.participant_saved'));
    }
}
