<?php

namespace App\Http\Controllers\Teaching;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Staff;
use App\Services\TeacherReportService;
use App\Support\Csv;
use App\Support\Paging;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** گزارش کار معلمان (مرحله‌ی ۱۶؛ teaching.manage): فهرست همه با چاپ و CSV، و صفحه‌ی هر معلم. */
class TeacherReportController extends Controller
{
    public function __construct(private readonly TeacherReportService $reports) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $print = $request->boolean('print');
        $rows = $year ? $this->reports->overview($year) : collect();

        return view('teaching.reports', [
            'year' => $year,
            'rows' => $print ? $rows : Paging::collection($rows),
            'print' => $print,
        ]);
    }

    public function show(Request $request, Staff $staff): View
    {
        $year = AcademicYear::current();
        abort_unless($year, 404);

        return view('teaching.report-show', [
            'year' => $year,
            'report' => $this->reports->detail($staff, $year),
            'print' => $request->boolean('print'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $year = AcademicYear::current();
        $rows = ($year ? $this->reports->overview($year) : collect())->map(fn ($r) => [
            $r['teacher']->full_name, $r['subjects'], $r['hours'], $r['present'], $r['absent'], $r['leave'],
            $r['marks_percent'], $r['plans'], $r['plans_approved'], $r['plan_percent'], $r['overdue'],
        ]);

        return Csv::download('teacher-report.csv', array_values(__('teaching.report_columns')), $rows);
    }
}
