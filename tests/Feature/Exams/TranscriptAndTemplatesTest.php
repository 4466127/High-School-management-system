<?php

namespace Tests\Feature\Exams;

use App\Models\ActivityLog;
use App\Models\GradeLevel;
use App\Models\Setting;
use App\Services\PastResultService;
use App\Services\TranscriptService;

/** مرحله‌ی ۱۴: قالب کارنامه و ترانسکریپت، ترانسکریپت چندساله، مرور پایان سال */
class TranscriptAndTemplatesTest extends ExamTestCase
{
    private function finalizeSection(): void
    {
        $this->enterAllMarks();
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))->assertSessionHasNoErrors();
    }

    public function test_report_card_follows_template_settings(): void
    {
        $this->enterAllMarks();
        $url = route('exams.reportcards.show', $this->students[0]);

        // پیش‌فرض: ستون امتحان‌ها، حاضری، امضای نگران صنف و مدیر
        $this->actingAs($this->principal)->get($url)->assertOk()
            ->assertSee($this->mid->name)->assertSee(__('exams.attendance_days'))->assertSee('امضای نگران صنف');

        $this->actingAs($this->principal)->put(route('exams.templates.update'), [
            'reportcard_title' => 'پارچه‌ی نتایج سالانه',
            'reportcard_subtitle' => 'دوره‌ی متوسطه',
            'reportcard_signature_1' => 'امضای سرمعلم',
            'reportcard_signature_2' => '',
            'reportcard_footer' => 'بدون مهر اعتبار ندارد',
            'reportcard_show_remarks' => '1',
            'reportcard_show_stamp' => '1',
            // ستون امتحان‌ها، حاضری، درجه و قاعده‌ها خاموش (تیک نخورده)
            'transcript_title' => 'سوانح سه‌ساله',
            'transcript_show_stamp' => '1',
        ])->assertRedirect(route('exams.templates.index'));

        $this->assertSame('0', Setting::get('reportcard_show_exams'));
        $this->assertTrue(ActivityLog::where('action', 'settings.exam_templates')->exists());

        $page = $this->actingAs($this->principal)->get($url)->assertOk();
        $page->assertSee('پارچه‌ی نتایج سالانه')->assertSee('دوره‌ی متوسطه')->assertSee('امضای سرمعلم')->assertSee('بدون مهر اعتبار ندارد')
            ->assertSee(__('exam_templates.remarks'))->assertSee(__('letters.stamp'))
            ->assertDontSee($this->mid->name)->assertDontSee(__('exams.attendance_days'))->assertDontSee('امضای نگران صنف');
    }

    public function test_template_page_and_previews_use_sample_data(): void
    {
        $this->actingAs($this->principal)->get(route('exams.templates.index'))->assertOk()->assertSee(__('exam_templates.section_transcript'));
        $this->actingAs($this->principal)->get(route('exams.templates.preview', ['type' => 'reportcard']))
            ->assertOk()->assertSee(__('exam_templates.sample_notice'))->assertSee('احمد');
        // پیش‌فرض: فورم رسمی وزارت معارف (نمرات سه ساله)
        $this->actingAs($this->principal)->get(route('exams.templates.preview', ['type' => 'transcript']))
            ->assertOk()->assertSee(__('moe_transcript.title'))->assertSee('بیولوژی')->assertSee('هشتاد و پنج')->assertSee('assets/images/moe/emblem.png', false);
        Setting::put('transcript_layout', 'simple');
        $this->actingAs($this->principal)->get(route('exams.templates.preview', ['type' => 'transcript']))
            ->assertOk()->assertSee(__('transcripts.default_title'))->assertDontSee(__('moe_transcript.title'));

        $this->actingAs($this->principal)->put(route('exams.templates.update'), ['transcript_grades' => [999999]])->assertSessionHasErrors('transcript_grades.0');
        $this->actingAs($this->teacher)->get(route('exams.templates.index'))->assertForbidden();
    }

    public function test_transcript_combines_system_results_and_manual_past_results(): void
    {
        $g6 = GradeLevel::where('sort_order', 6)->firstOrFail();
        $g7 = $this->section->gradeLevel;
        $g8 = GradeLevel::where('sort_order', 8)->firstOrFail();
        Setting::put('transcript_grade_ids', "{$g8->id},{$g6->id},{$g7->id}");
        $this->finalizeSection();

        $student = $this->students[0]->student;
        app(PastResultService::class)->create($student, [
            'grade_level_id' => $g6->id, 'year_name' => '1404', 'school_name' => 'لیسه‌ی قبلی',
            'subjects' => [['name' => 'ریاضی', 'mark' => 80], ['name' => 'دری', 'mark' => 70], ['name' => 'تاریخ', 'mark' => 30]],
        ], $this->principal);

        $data = app(TranscriptService::class)->build($student);
        $this->assertSame([$g6->id, $g7->id, $g8->id], array_map(fn ($c) => $c['level']->id, $data['columns']));
        $this->assertSame(['manual', 'system', 'missing'], array_column($data['columns'], 'source'));
        $this->assertSame(60.0, $data['columns'][0]['average']);
        $this->assertSame('conditional', $data['columns'][0]['result']);   // یک مضمون ناکام، حد مشروط ۱
        $this->assertSame(84.0, $data['columns'][1]['average']);
        $math = collect($data['rows'])->firstWhere('name', 'ریاضی');
        $this->assertSame([80.0, 90.0, null], $math['marks']);
        $this->assertFalse($data['complete']);

        $this->actingAs($this->principal)->get(route('exams.transcripts.student', [$student, 'layout' => 'simple']))->assertOk()
            ->assertSee('لیسه‌ی قبلی')->assertSee('تاریخ')->assertSee(__('transcripts.missing'));
        // فورم رسمی: سطر «الجبر/ هندسه» نمره‌ی ریاضی را دارد (به عدد و به حروف)، «لسان اول» = دری
        $official = app(TranscriptService::class)->officialRows($data);
        $this->assertCount(22, $official);
        $this->assertSame([80.0, 90.0, null], collect($official)->firstWhere('key', 'math')['marks']);
        $this->assertSame(70.0, collect($official)->firstWhere('key', 'language_1')['marks'][0]);
        $this->actingAs($this->principal)->get(route('exams.transcripts.student', $student))->assertOk()
            ->assertSee(__('moe_transcript.title'))->assertSee(__('moe_transcript.subjects.math'))->assertSee('هشتاد')->assertSee($student->father_name);
        $this->actingAs($this->principal)->get(route('exams.transcripts.section', $this->section))->assertOk()
            ->assertSee($this->students[2]->student->full_name);
        $this->actingAs($this->principal)->get(route('exams.transcripts.index', ['q' => $student->first_name]))->assertOk()->assertSee($student->full_name);
    }

    public function test_transcript_access(): void
    {
        $this->actingAs($this->teacher)->get(route('exams.transcripts.student', $this->students[0]->student))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('exams.transcripts.section', $this->otherSection))->assertForbidden();
    }

    public function test_year_review_shows_next_step_per_section(): void
    {
        $page = $this->actingAs($this->principal)->get(route('exams.year-review.index'))->assertOk();
        $page->assertSee($this->section->label)->assertSee(__('year_review.step.marks'))->assertSee(__('year_review.next_year_missing'));

        $this->finalizeSection();
        $this->makeYear('1406', '2027-03-21', '2027-12-21', current: false);
        $page = $this->actingAs($this->principal)->get(route('exams.year-review.index'))->assertOk();
        // شاگرد دوم مشروط است ← گام بعدی امتحان مشروطی
        $page->assertSee(__('year_review.step.reexam'))->assertSee(__('year_review.next_year_ready', ['year' => '۱۴۰۶']));
        $this->actingAs($this->principal)->get(route('exams.year-review.index', ['print' => 1]))->assertOk();
        $this->actingAs($this->teacher)->get(route('exams.year-review.index'))->assertForbidden();
    }
}
