<?php

namespace App\Http\Controllers\Exams;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ExamRequest;
use App\Http\Requests\Exams\ExamTypeRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamType;
use App\Services\ActivityLogger;
use App\Support\ModelChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** تعریف امتحان‌های سال جاری، باز/قفل کردن آن‌ها، و انواع امتحان (مجوز: exams.manage). */
class ExamController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();

        return view('exams.manage.index', [
            'year' => $year,
            'exams' => $year
                ? Exam::query()->with('type')->withCount('marks')->where('academic_year_id', $year->id)->ordered()->get()
                : collect(),
            'types' => ExamType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'yearFinalized' => $year && $this->yearFinalized($year->id),
        ]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $year = AcademicYear::current();
        abort_unless($year, 404);

        $exam = Exam::create($request->examData() + ['academic_year_id' => $year->id, 'status' => ExamStatus::Draft]);
        $this->logger->log('exam.created', $exam, $exam->name.' — '.$year->name, new: [
            'max_mark' => $exam->max_mark, 'counts_in_final' => $exam->counts_in_final,
        ]);

        return redirect()->route('exams.manage.index')->with('success', __('exams.exam_saved', ['name' => $exam->name]));
    }

    public function edit(Exam $exam): View
    {
        return view('exams.manage.edit', [
            'exam' => $exam,
            'types' => ExamType::query()->where('is_active', true)->orWhere('id', $exam->exam_type_id)->orderBy('sort_order')->get(),
            'yearFinalized' => $this->yearFinalized($exam->academic_year_id),
        ]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $data = $request->examData();
        $exam->fill($data);

        $scoring = $exam->isDirty('max_mark') || $exam->isDirty('weight') || $exam->isDirty('counts_in_final');
        if ($scoring && $this->yearFinalized($exam->academic_year_id)) {
            return back()->withInput()->with('error', __('exams.scoring_locked_finalized'));
        }
        $highest = (float) $exam->marks()->max('mark');
        if ($exam->isDirty('max_mark') && $highest > $data['max_mark']) {
            return back()->withInput()->with('error', __('exams.max_below_marks', ['mark' => $highest]));
        }

        [$old, $new] = ModelChanges::capture($exam);
        $exam->save();
        if ($new !== []) {
            $this->logger->log('exam.updated', $exam, $exam->name, $old, $new);
        }

        return redirect()->route('exams.manage.index')->with('success', __('exams.exam_saved', ['name' => $exam->name]));
    }

    public function status(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ExamStatus::class)]]);
        $new = ExamStatus::from($data['status']);
        if ($new === $exam->status) {
            return back();
        }

        $old = $exam->status;
        $exam->update(['status' => $new]);
        $this->logger->log('exam.status_changed', $exam, $exam->name, ['status' => $old->label()], ['status' => $new->label()]);

        return back()->with('success', __('exams.status_changed', ['name' => $exam->name, 'status' => $new->label()]));
    }

    /** فقط امتحانی که هیچ نمره‌ای ندارد حذف می‌شود */
    public function destroy(Exam $exam): RedirectResponse
    {
        if ($exam->marks()->exists()) {
            return back()->with('error', __('exams.exam_has_marks'));
        }

        DB::transaction(function () use ($exam) {
            $name = $exam->name;
            $exam->delete();
            $this->logger->log('exam.deleted', null, $name);
        });

        return redirect()->route('exams.manage.index')->with('success', __('exams.exam_deleted'));
    }

    public function storeType(ExamTypeRequest $request): RedirectResponse
    {
        $type = ExamType::create($request->typeData());
        $this->logger->log('exam_type.created', $type, $type->name);

        return redirect()->to(route('exams.manage.index').'#types-pane')->with('success', __('exams.type_saved', ['name' => $type->name]));
    }

    public function updateType(ExamTypeRequest $request, ExamType $type): RedirectResponse
    {
        $type->fill($request->typeData());
        [$old, $new] = ModelChanges::capture($type);
        $type->save();
        if ($new !== []) {
            $this->logger->log('exam_type.updated', $type, $type->name, $old, $new);
        }

        return redirect()->to(route('exams.manage.index').'#types-pane')->with('success', __('exams.type_saved', ['name' => $type->name]));
    }

    private function yearFinalized(int $yearId): bool
    {
        return Enrollment::where('academic_year_id', $yearId)->whereNotNull('finalized_at')->exists();
    }
}
