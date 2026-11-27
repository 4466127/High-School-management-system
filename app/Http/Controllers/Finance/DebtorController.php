<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\FinanceService;
use App\Support\Csv;
use App\Support\Money;
use App\Support\Paging;
use App\Support\PersianText;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** بدهکاران سال جاری (مانده‌ی بل‌ها بیشتر از صفر)؛ چاپ و CSV. */
class DebtorController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(Request $request): View
    {
        $data = $this->load($request);
        $print = $request->boolean('print');
        if (! $print) {
            $data['rows'] = Paging::collection($data['rows']);
        }

        return view('finance.debtors.index', $data + ['print' => $print, 'q' => (string) $request->query('q', '')]);
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
        // جستجو: هر واژه در نام، نام پدر یا نمبر اساس
        foreach (PersianText::searchTerms($request->query('q')) as $word) {
            $rows = $rows->filter(function ($r) use ($word) {
                $s = $r->enrollment->student;

                return str_contains(str_replace(PersianText::ZWNJ, '', $s->full_name.' '.$s->father_name.' '.$s->admission_no), $word);
            });
        }
        $rows = $rows->values();

        return [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'rows' => $rows,
            'sum' => $rows->sum('remaining'),
        ];
    }
}
