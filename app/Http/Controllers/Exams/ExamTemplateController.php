<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\ExamTemplateRequest;
use App\Models\GradeLevel;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\TranscriptService;
use App\Support\ExamTemplate;
use App\Support\ExamTemplateSample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * قالب کارنامه و ترانسکریپت (مرحله‌ی ۱۴؛ exams.manage): عنوان، ستون‌ها، امضاها، مهر، یادداشت پایین.
 * «پیش‌نمایش» همان صفحه‌ی چاپ را با دانش‌آموز نمونه (ساختگی، بدون پایگاه داده) نشان می‌دهد.
 */
class ExamTemplateController extends Controller
{
    public function edit(TranscriptService $transcripts): View
    {
        $keys = [...ExamTemplate::REPORT_CARD_KEYS, ...ExamTemplate::TRANSCRIPT_KEYS];

        return view('exams.templates.edit', [
            'values' => collect($keys)->mapWithKeys(fn ($k) => [$k => (string) Setting::get($k, '')])->all(),
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(),
            'transcriptGrades' => $transcripts->gradeLevels()->pluck('id')->all(),
        ]);
    }

    public function update(ExamTemplateRequest $request, ActivityLogger $logger): RedirectResponse
    {
        DB::transaction(function () use ($request, $logger) {
            $old = [];
            $new = [];
            foreach ($request->settings() as $key => $value) {
                $before = (string) Setting::get($key, '');
                if ($before !== $value) {
                    $old[$key] = $before;
                    $new[$key] = $value;
                    Setting::put($key, $value);
                }
            }
            if ($new !== []) {
                $logger->log('settings.exam_templates', null, __('exam_templates.title'), $old, $new);
            }
        });

        return redirect()->route('exams.templates.index')->with('success', __('exam_templates.saved'));
    }

    public function preview(Request $request, TranscriptService $transcripts): View
    {
        if ($request->query('type') === 'transcript') {
            return view('exams.transcripts.print', [
                'items' => [ExamTemplateSample::transcript($transcripts->gradeLevels())],
                'tpl' => ExamTemplate::transcript(),
                'sample' => true,
            ]);
        }

        [$section, $cards] = ExamTemplateSample::reportCard();

        return view('exams.reportcards.print', ['section' => $section, 'cards' => $cards, 'sample' => true]);
    }
}
