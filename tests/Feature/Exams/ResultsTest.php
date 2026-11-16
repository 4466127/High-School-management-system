<?php

namespace Tests\Feature\Exams;

use App\Enums\FinalResult;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;

/** جدول نتایج، نهایی‌کردن، کارنامه و پیشنهاد ارتقا. */
class ResultsTest extends ExamTestCase
{
    public function test_results_table_computes_finals_results_and_grade(): void
    {
        $this->enterAllMarks();

        $html = $this->actingAs($this->principal)->get(route('exams.results.index', ['section' => $this->section->id]))
            ->assertOk()
            ->assertSee('شاگرداول')
            ->assertSee(__('exams.provisional_notice', ['exams' => 'امتحان چهارونیم‌ماهه، امتحان سالانه']))
            ->getContent();

        $this->assertStringContainsString('۸۴', $html);         // اوسط شاگرد اول
        $this->assertStringContainsString('۴۷.۵', $html);       // اوسط شاگرد دوم
        $this->assertStringContainsString('کامیاب', $html);
        $this->assertStringContainsString('مشروط', $html);
        $this->assertStringContainsString('ناکام', $html);

        // نمایش یک امتحان
        $this->actingAs($this->principal)->get(route('exams.results.index', ['section' => $this->section->id, 'exam' => $this->mid->id]))
            ->assertOk()->assertSee('۶۶'); // ۳۶ + ۳۰
    }

    public function test_incomplete_marks_show_as_incomplete(): void
    {
        $this->enterMarks($this->mid, $this->math, [36, 10, 5]);

        $this->actingAs($this->principal)->get(route('exams.results.index', ['section' => $this->section->id]))
            ->assertOk()->assertSee(__('exams.incomplete'));
    }

    public function test_finalize_needs_locked_exams_and_complete_marks(): void
    {
        $this->enterMarks($this->mid, $this->math, [36, 10, 5]);

        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))->assertSessionHas('error', __('exams.finalize_unlocked'));
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))
            ->assertSessionHas('error', __('exams.finalize_incomplete', ['count' => '۳']));
        $this->assertNull($this->students[0]->fresh()->finalized_at);

        $this->actingAs($this->teacher)->post(route('exams.results.finalize', $this->section))->assertForbidden();
    }

    public function test_finalize_stores_results_and_settings_changes_do_not_alter_them(): void
    {
        $this->enterAllMarks();
        $this->lockExams();

        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))->assertSessionHas('success');

        $first = $this->students[0]->fresh();
        $this->assertSame(FinalResult::Passed, $first->final_result);
        $this->assertSame('84.00', $first->final_average);
        $this->assertSame('168.00', $first->final_total);
        $this->assertSame('b', $first->final_grade);  // اوسط ۸۴ ← ب
        $this->assertSame(40.0, (float) $first->final_snapshot['settings']['pass_percent']);
        $this->assertSame(FinalResult::Conditional, $this->students[1]->fresh()->final_result);
        $this->assertSame(FinalResult::Failed, $this->students[2]->fresh()->final_result);
        $this->assertNull($this->students[2]->fresh()->final_grade);
        $this->assertTrue(ActivityLog::where('action', 'results.finalized')->exists());

        // تغییر تنظیمات و نمره‌ی کامل بعد از نهایی‌کردن، نتیجه‌ی ذخیره‌شده و کارنامه را عوض نمی‌کند
        Setting::put('pass_percent', '80');
        $this->actingAs($this->principal)->get(route('exams.reportcards.show', $this->students[1]))
            ->assertOk()->assertSee('مشروط')->assertDontSee(__('exams.provisional'));
        $this->actingAs($this->principal)->get(route('exams.results.index', ['section' => $this->section->id]))
            ->assertOk()->assertSee(__('exams.finalized_notice'))->assertSee('کامیاب');

        // نمره‌ی بخش نهایی‌شده تغییر نمی‌کند
        $this->mid->update(['status' => 'open']);
        $this->enterMarks($this->mid, $this->math, [10, 10, 10])->assertSessionHas('error', __('exams.blocked_finalized'));

        // وزن امتحان هم تغییر نمی‌کند
        $this->actingAs($this->principal)->put(route('exams.manage.update', $this->mid), [
            'exam_type_id' => $this->mid->exam_type_id, 'name' => $this->mid->name, 'max_mark' => '40', 'weight' => '50', 'counts_in_final' => '1',
        ])->assertSessionHas('error', __('exams.scoring_locked_finalized'));
    }

    public function test_unfinalize_requires_reason_and_reopens(): void
    {
        $this->enterAllMarks();
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section));

        $this->actingAs($this->principal)->post(route('exams.results.unfinalize', $this->section), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->principal)->post(route('exams.results.unfinalize', $this->section), ['reason' => 'اصلاح نمره‌ی دری'])->assertSessionHas('success');

        $this->assertNull($this->students[0]->fresh()->finalized_at);
        $this->assertNull($this->students[0]->fresh()->final_result);
        $this->assertStringContainsString('اصلاح نمره‌ی دری', ActivityLog::where('action', 'results.unfinalized')->sole()->description);
    }

    public function test_deprived_students_from_attendance(): void
    {
        Setting::put('deprivation_absence_days', '1');
        $attendance = app(AttendanceService::class);
        foreach (['2026-11-14', '2026-11-15'] as $day) {
            $statuses = [];
            foreach ($this->students as $i => $e) {
                $statuses[$e->id] = $i === 0 ? 'absent' : 'present';
            }
            $attendance->saveSection($this->section, CarbonImmutable::parse($day), $statuses, [], $this->principal);
        }
        $this->enterAllMarks();
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section))->assertSessionHas('success');

        $this->assertSame(FinalResult::Deprived, $this->students[0]->fresh()->final_result);
        $this->assertSame(2, $this->students[0]->fresh()->final_snapshot['attendance']['absent']);
    }

    public function test_report_cards_single_and_group_with_scope(): void
    {
        $this->enterAllMarks();

        $this->actingAs($this->principal)->get(route('exams.reportcards.show', $this->students[0]))
            ->assertOk()->assertSee('شاگرداول')->assertSee(__('exams.provisional'))->assertSee('۹۰')->assertSee(__('app.signature_principal'));

        $this->actingAs($this->principal)->get(route('exams.reportcards.section', $this->section))
            ->assertOk()->assertSee('شاگرداول')->assertSee('شاگردسوم');

        $this->actingAs($this->userWithRole(Role::Office))->get(route('exams.reportcards.index', ['section' => $this->section->id]))
            ->assertOk()->assertSee(route('exams.reportcards.show', $this->students[0]));

        // معلم فقط بخش خودش؛ حسابدار هیچ
        $this->actingAs($this->teacher)->get(route('exams.reportcards.show', $this->students[0]))->assertOk();
        $other = Enrollment::where('section_id', $this->otherSection->id)->firstOrFail();
        $this->actingAs($this->teacher)->get(route('exams.reportcards.show', $other))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('exams.reportcards.index'))->assertForbidden();
    }

    public function test_results_csv_export(): void
    {
        $this->enterAllMarks();
        $csv = $this->actingAs($this->principal)->get(route('exams.results.export', ['section' => $this->section->id]))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ریاضی', $csv);
        $this->assertStringContainsString(',90,78,168,84,کامیاب,ب', $csv);
    }

    public function test_promotion_suggests_decisions_from_final_results(): void
    {
        $this->enterAllMarks();
        $this->lockExams();
        $this->actingAs($this->principal)->post(route('exams.results.finalize', $this->section));

        $next = AcademicYear::create(['name' => '1406', 'start_date' => '2027-03-21', 'end_date' => '2027-12-21', 'status' => 'open']);
        $html = $this->actingAs($this->principal)->get(route('academics.promotion.create', ['section' => $this->section->id, 'target_year' => $next->id]))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~id="d'.$this->students[0]->id.'promote" value="promote"\s+checked~', $html);
        $this->assertMatchesRegularExpression('~id="d'.$this->students[1]->id.'skip" value="skip"\s+checked~', $html);
        $this->assertMatchesRegularExpression('~id="d'.$this->students[2]->id.'repeat" value="repeat"\s+checked~', $html);
    }

    public function test_student_profile_shows_marks_tab(): void
    {
        $this->enterAllMarks();
        $this->actingAs($this->principal)->get(route('students.show', $this->students[0]->student))
            ->assertOk()->assertSee(__('exams.tab'))->assertSee('۹۰')->assertSee(route('exams.reportcards.show', $this->students[0]));

        // حسابدار نمرات را نمی‌بیند
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('students.show', $this->students[0]->student))
            ->assertOk()->assertDontSee(route('exams.reportcards.show', $this->students[0]));
    }
}
