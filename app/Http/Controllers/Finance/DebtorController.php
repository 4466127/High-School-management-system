<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\FinanceService;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** بدهکاران سال جاری (مانده‌ی بل‌ها بیشتر از صفر)؛ چاپ و CSV. */
class DebtorController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(Request $request): View
    {
        return view('finance.debtors.index', $this->load($request) + ['print' => $request->boolean('print')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->load($request)['rows']->map(fn ($r) => [
            $r->enrollment->section->label, $r->enrollment->roll_no, $r->enrollment->student->full_name, $r->enrollment->student->father_name,
            $r->enrollment->student->phone, $r->invoices, Money::plain(Money::fromCents($r->total)), Money::plain(Money::fromCents($r->discount)),
            Money::plain(Money::fromCents($r->paid)), Money::plain(Money::fromCents($r->remaining)),
        ]);

        return Csv::download('debtors.csv', [
            __('academics.section'), __('academics.roll_no'), __('students.full_name'), __('students.father_name'), __('students.phone'),
            __('finance.invoices_count'), __('finance.total'), __('finance.discount'), __('finance.paid'), __('finance.remaining'),
        ], $rows);
    }

    private function load(Request $request): array
    {
        $year = AcademicYear::current();
        $sections = $year ? Section::with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get() : collect();
        $section = $sections->firstWhere('id', $request->integer('section'));
        $rows = $year ? $this->finance->debtors($year, $section ? [$section->id] : null) : collect();

        return [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'rows' => $rows,
            'sum' => $rows->sum('remaining'),
        ];
    }
}
