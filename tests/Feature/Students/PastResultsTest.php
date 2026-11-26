<?php

namespace Tests\Feature\Students;

use App\Enums\StudentStatus;
use App\Models\ActivityLog;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentPastResult;
use App\Services\ImportService;
use App\Services\LetterService;
use App\Support\Xlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۴: نتیجه‌ی سال‌های قبل (درج دستی)، ثبت دانش‌آموز سابق، ورود گروهی فارغ‌ها، راهنمای پیش‌نویس مکتوب */
class PastResultsTest extends ExamTestCase
{
    public function test_past_result_form_prefills_subjects_and_computes_totals(): void
    {
        $student = $this->students[0]->student;
        $grade = $this->section->gradeLevel;

        $this->actingAs($this->principal)->get(route('student-past-results.create', [$student, 'grade' => $grade->id]))
            ->assertOk()->assertSee('value="ریاضی"', false)->assertSee('value="دری"', false);

        $this->actingAs($this->principal)->post(route('student-past-results.store', $student), [
            'grade_level_id' => $grade->id, 'year_name' => '۱۴۰۲',
            'subjects' => [['name' => 'ریاضی', 'mark' => '۹۰'], ['name' => 'دری', 'mark' => '80'], ['name' => '', 'mark' => '']],
        ])->assertRedirect();

        $row = StudentPastResult::latest('id')->firstOrFail();
        $this->assertSame('1402', $row->year_name);
        $this->assertSame($grade->name, $row->grade_name);
        $this->assertSame('170.00', $row->total);
        $this->assertSame('85.00', $row->average);
        $this->assertSame('passed', $row->result->value);
        $this->assertSame('b', $row->grade_letter);
        $this->assertCount(2, $row->subjects);
        $this->assertTrue(ActivityLog::where('action', 'student.past_result_added')->exists());

        $this->actingAs($this->principal)->get(route('students.show', $student))->assertSee(__('past_results.list_title'))->assertSee('۱۴۰۲');

        // ویرایش و باطل کردن (حذف نیست)
        $this->actingAs($this->principal)->put(route('student-past-results.update', $row), [
            'grade_level_id' => $grade->id, 'year_name' => '1402', 'average' => '91', 'result' => 'passed',
        ])->assertRedirect();
        $this->assertSame('91.00', $row->fresh()->average);
        $this->assertSame('a', $row->fresh()->grade_letter);

        $this->actingAs($this->principal)->post(route('student-past-results.cancel', $row), ['reason' => 'سال اشتباه'])->assertRedirect();
        $this->assertNotNull($row->fresh()->cancelled_at);
        $this->assertSame(1, StudentPastResult::count());
        $this->actingAs($this->principal)->post(route('student-past-results.cancel', $row), ['reason' => 'دوباره'])->assertSessionHasErrors('reason');
    }

    public function test_past_result_validation(): void
    {
        $student = $this->students[0]->student;
        $this->actingAs($this->principal)->post(route('student-past-results.store', $student), [
            'year_name' => '99', 'subjects' => [['name' => 'ریاضی', 'mark' => '']],
        ])->assertSessionHasErrors(['year_name', 'grade_level_id', 'subjects.0.mark']);
        $this->actingAs($this->teacher)->get(route('student-past-results.create', $student))->assertForbidden();
    }

    public function test_former_graduate_is_created_in_archive_with_results(): void
    {
        $g12 = GradeLevel::where('sort_order', 12)->firstOrFail();
        $this->actingAs($this->principal)->get(route('student-archive.create'))->assertOk()->assertSee(__('past_results.former_results'));

        $this->actingAs($this->principal)->post(route('student-archive.store'), [
            'first_name' => 'فارغ', 'last_name' => 'قدیمی', 'father_name' => 'پدرش', 'gender' => 'male',
            'status' => 'graduated', 'left_on' => '1401/09/30', 'certificate_no' => 'F-1401-5',
            'results' => [
                ['grade_level_id' => $g12->id, 'year_name' => '1401', 'average' => '84.5', 'result' => ''],
                ['grade_level_id' => GradeLevel::where('sort_order', 11)->value('id'), 'year_name' => '', 'average' => ''],
            ],
        ])->assertRedirect();

        $student = Student::latest('id')->firstOrFail();
        $this->assertSame(StudentStatus::Graduated, $student->status);
        $this->assertSame('F-1401-5', $student->certificate_no);
        $this->assertSame('2022-12-21', $student->status_changed_on->toDateString());
        $this->assertSame(0, $student->enrollments()->count());
        $this->assertSame(1, $student->pastResults()->count());
        $this->assertSame('passed', $student->pastResults()->first()->result->value);
        $this->assertTrue(ActivityLog::where('action', 'student.former_added')->exists());

        $this->actingAs($this->principal)->get(route('student-archive.index'))->assertSee('فارغ قدیمی')->assertSee('۸۴.۵');

        // سال لازم است وقتی اوسط نوشته شده
        $this->actingAs($this->principal)->post(route('student-archive.store'), [
            'first_name' => 'دیگر', 'father_name' => 'x', 'gender' => 'female', 'status' => 'graduated',
            'results' => [['grade_level_id' => $g12->id, 'year_name' => '', 'average' => '70']],
        ])->assertSessionHasErrors('results.0.year_name');
    }

    public function test_graduates_import_from_excel(): void
    {
        Storage::fake('local');
        $service = app(ImportService::class);
        $this->actingAs($this->principal)->get(route('import.template', 'graduates'))->assertOk();

        $headers = array_map(fn ($c) => $c[0], array_values($service->columns('graduates')));
        $row = fn (array $values) => array_map(fn ($key) => $values[$key] ?? '', array_keys($service->columns('graduates')));
        $content = Xlsx::build([['name' => 'x', 'rows' => [
            $headers,
            $row(['first_name' => 'نعیم', 'father_name' => 'رحیم', 'gender' => 'پسر', 'graduation_year' => '1401', 'certificate_no' => 'F-9', 'avg_10' => '70', 'avg_11' => '75', 'avg_12' => '80']),
            $row(['first_name' => 'زهره', 'father_name' => 'کریم', 'gender' => 'دختر', 'status' => 'ترک تحصیل']),
            $row(['first_name' => 'بد', 'father_name' => 'x', 'gender' => 'پسر', 'avg_12' => '80']),
        ]]]);
        $file = UploadedFile::fake()->createWithContent('g.xlsx', $content);

        $upload = $this->actingAs($this->principal)->post(route('import.upload'), ['type' => 'graduates', 'file' => $file]);
        $upload->assertRedirect();
        parse_str((string) parse_url($upload->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->actingAs($this->principal)->get($upload->headers->get('Location'))->assertOk()->assertSee(__('import.average_needs_year'));

        $this->actingAs($this->principal)->post(route('import.store'), ['type' => 'graduates', 'token' => $query['token']])
            ->assertRedirect(route('student-archive.index'));

        $naim = Student::where('first_name', 'نعیم')->firstOrFail();
        $this->assertSame(StudentStatus::Graduated, $naim->status);
        $this->assertSame(['1399', '1400', '1401'], $naim->pastResults()->pluck('year_name')->all());
        $this->assertSame(StudentStatus::Dropped, Student::where('first_name', 'زهره')->firstOrFail()->status);
        $this->assertFalse(Student::where('first_name', 'بد')->exists());
    }

    public function test_draft_letter_explains_how_to_issue(): void
    {
        $letter = app(LetterService::class)->createOutgoing(['subject' => 'آزمایش', 'body' => 'متن', 'recipient' => 'ریاست', 'letter_date' => '2026-11-16', 'type' => 'general'], $this->principal);
        $this->actingAs($this->principal)->get(route('letters.outgoing.show', $letter))->assertSee(__('letters.draft_help'));
        $this->actingAs($this->principal)->get(route('letters.outgoing.show', [$letter, 'print' => 1]))->assertSee(__('letters.draft_help'));
    }
}
