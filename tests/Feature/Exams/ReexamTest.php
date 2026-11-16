<?php

namespace Tests\Feature\Exams;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use App\Models\ActivityLog;
use App\Models\ReexamMark;

/** امتحان مشروطی: شاگرد دوم (ریاضی ۲۵، دری ۷۰) مشروط است. */
class ReexamTest extends ExamTestCase
{
    private function finalizeSection(): void
    {
        $this->enterAllMarks();
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))->assertSessionHas('success');
    }

    private function saveReexam(string $mark, bool $absent = false)
    {
        $id = $this->students[1]->id;

        return $this->actingAs($this->principal)->post(route('exams.reexam.store', $this->section), [
            'marks' => [$id => [$this->math->id => $mark]],
            'absent' => [$id => [$this->math->id => $absent ? '1' : '0']],
        ]);
    }

    public function test_page_needs_finalized_results_and_lists_only_conditional_students(): void
    {
        $this->actingAs($this->principal)->get(route('exams.reexam.index', ['section' => $this->section->id]))
            ->assertOk()->assertSee(__('exams.reexam_needs_final'));

        $this->finalizeSection();

        $this->actingAs($this->principal)->get(route('exams.reexam.index', ['section' => $this->section->id]))
            ->assertOk()
            ->assertSee('شاگرددوم')
            ->assertDontSee('شاگرداول')
            ->assertDontSee('شاگردسوم')
            ->assertSee('ریاضی')
            ->assertSee('۲۵');

        $this->actingAs($this->teacher)->get(route('exams.reexam.index'))->assertForbidden();
    }

    public function test_passing_reexam_makes_student_passed_with_grade(): void
    {
        $this->finalizeSection();

        $this->saveReexam('۱۲۰')->assertSessionHasErrors('marks.'.$this->students[1]->id.'.'.$this->math->id);
        $this->saveReexam('60')->assertSessionHas('success');
        $this->assertSame('60.00', ReexamMark::sole()->mark);

        $this->actingAs($this->principal)->post(route('exams.reexam.finalize', $this->section))->assertSessionHas('success');

        $e = $this->students[1]->fresh();
        $this->assertSame(FinalResult::Passed, $e->final_result);
        $this->assertSame('65.00', $e->final_average); // (۶۰ + ۷۰) ÷ ۲
        $this->assertSame('c', $e->final_grade);
        $this->assertNotNull($e->reexam_finalized_at);
        $this->assertSame('conditional', $e->final_snapshot['reexam']['original']['result']);
        $this->assertTrue(ActivityLog::where('action', 'reexam.finalized')->exists());

        // کارنامه ستون مشروطی و درجه را نشان می‌دهد؛ جدول نتایج هم
        $this->actingAs($this->principal)->get(route('exams.reportcards.show', $e))
            ->assertOk()->assertSee(__('exams.reexam_column'))->assertSee(__('exams.after_reexam'));
        $this->actingAs($this->principal)->get(route('exams.results.index', ['section' => $this->section->id]))
            ->assertOk()->assertSee(__('exams.after_reexam'));

        // بعد از نهایی‌شدن، نمره تغییر نمی‌کند و نهایی‌کردن سال لغو نمی‌شود
        $this->saveReexam('20');
        $this->assertSame('60.00', ReexamMark::sole()->fresh()->mark);
        $this->actingAs($this->principal)->post(route('exams.results.unfinalize', $this->section), ['reason' => 'آزمایش'])
            ->assertSessionHas('error', __('exams.unfinalize_reexam_first'));
    }

    public function test_failing_or_absent_reexam_makes_student_failed(): void
    {
        $this->finalizeSection();

        // ناقص ← نهایی نمی‌شود
        $this->actingAs($this->principal)->post(route('exams.reexam.finalize', $this->section))
            ->assertSessionHas('error', __('exams.reexam_incomplete', ['count' => '۱']));

        $this->saveReexam('', true);
        $this->actingAs($this->principal)->post(route('exams.reexam.finalize', $this->section))->assertSessionHas('success');

        $e = $this->students[1]->fresh();
        $this->assertSame(FinalResult::Failed, $e->final_result);
        $this->assertNull($e->final_grade);
    }

    public function test_unfinalize_restores_conditional_until_promotion(): void
    {
        $this->finalizeSection();
        $this->saveReexam('50');
        $this->actingAs($this->principal)->post(route('exams.reexam.finalize', $this->section));

        $this->actingAs($this->principal)->post(route('exams.reexam.unfinalize', $this->section), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->principal)->post(route('exams.reexam.unfinalize', $this->section), ['reason' => 'اصلاح نمره'])->assertSessionHas('success');

        $e = $this->students[1]->fresh();
        $this->assertSame(FinalResult::Conditional, $e->final_result);
        $this->assertSame('47.50', $e->final_average);
        $this->assertNull($e->reexam_finalized_at);
        $this->assertArrayNotHasKey('reexam', $e->final_snapshot);
        $this->assertFalse(collect($e->final_snapshot['subjects'])->firstWhere('id', $this->math->id)['passed']);

        // بعد از ارتقا لغو ممکن نیست
        $this->actingAs($this->principal)->post(route('exams.reexam.finalize', $this->section));
        $this->students[1]->fresh()->forceFill(['status' => EnrollmentStatus::Promoted])->save();
        $this->actingAs($this->principal)->post(route('exams.reexam.unfinalize', $this->section), ['reason' => 'اصلاح'])
            ->assertSessionHas('error', __('exams.reexam_already_promoted'));
    }
}
