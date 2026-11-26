<?php

namespace App\Http\Controllers\Students;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Rules\ValidJalaliDate;
use App\Services\TransferService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بایگانی دانش‌آموزان سابق (فارغ، منتقل‌شده، ترک تحصیل، غیرفعال) — مرحله‌ی ۱۳؛ students.manage.
 * فقط خواندن، به جز مشخصات سند فراغت. سوابق هیچ‌وقت پاک نمی‌شود.
 */
class ArchiveController extends Controller
{
    public const STATUSES = [StudentStatus::Graduated, StudentStatus::Transferred, StudentStatus::Dropped, StudentStatus::Inactive];

    public function index(Request $request): View
    {
        $print = $request->boolean('print');
        $students = $this->query($request);

        return view('students.archive', [
            'students' => $print ? $students->get() : $students->paginate(30)->withQueryString(),
            'filters' => $this->filters($request),
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'print' => $print,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->get()->map(function (Student $s) {
            $last = $s->enrollments->first();

            return [
                $s->admission_no, $s->full_name, $s->father_name, $s->gender?->studentLabel(), $s->status->label(),
                $last ? Digits::toLatin((string) $last->academicYear->name) : '', $last?->section?->gradeLevel?->name,
                $last?->final_result?->label(), $last?->final_average,
                $s->status_changed_on ? Digits::toLatin(JalaliDate::format($s->status_changed_on)) : '',
                $s->certificate_no, $s->status_reason,
            ];
        });

        return Csv::download('former-students.csv', [
            __('students.admission_no'), __('students.full_name'), __('students.father_name'), __('students.gender'), __('students.status'),
            __('archive.last_year'), __('archive.last_grade'), __('archive.last_result'), __('archive.last_average'),
            __('archive.left_on'), __('archive.certificate_no'), __('students.reason'),
        ], $rows);
    }

    public function certificate(Request $request, Student $student, TransferService $transfers): RedirectResponse
    {
        $data = $request->validate([
            'certificate_no' => ['nullable', 'string', 'max:100'],
            'certificate_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'certificate_note' => ['nullable', 'string', 'max:500'],
        ], [], __('archive.attributes'));
        $data['certificate_date'] = JalaliDate::parse($data['certificate_date'] ?? null)?->toDateString();
        $transfers->saveCertificate($student, $data, $request->user());

        return back()->with('success', __('archive.certificate_saved'));
    }

    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'year' => $request->integer('year') ?: null,
        ];
    }

    private function query(Request $request)
    {
        $status = StudentStatus::tryFrom((string) $request->query('status'));
        $status = in_array($status, self::STATUSES, true) ? $status : null;
        $year = $request->integer('year');

        return Student::query()
            ->whereIn('status', $status ? [$status] : self::STATUSES)
            ->search($request->query('q'))
            // سال خروج: سالی که شمولیت در آن فارغ، منتقل یا ترک شد
            ->when($year, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $year)
                ->whereIn('status', [EnrollmentStatus::Graduated, EnrollmentStatus::Transferred, EnrollmentStatus::Dropped])))
            ->with(['enrollments' => fn ($q) => $q->with(['academicYear', 'section.gradeLevel'])
                ->join('academic_years', 'academic_years.id', '=', 'enrollments.academic_year_id')
                ->orderByDesc('academic_years.start_date')->select('enrollments.*')])
            ->orderByDesc('status_changed_on')->orderBy('first_name');
    }
}
