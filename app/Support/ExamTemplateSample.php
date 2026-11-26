<?php

namespace App\Support;

use App\Enums\FinalResult;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ResultCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * داده‌ی نمونه‌ی ساختگی برای پیش‌نمایش قالب کارنامه و ترانسکریپت (مرحله‌ی ۱۴).
 * هیچ چیز در پایگاه داده ذخیره نمی‌شود؛ مدل‌ها فقط در حافظه ساخته می‌شوند.
 */
final class ExamTemplateSample
{
    /** مضمون ← [نمره‌ی چهارونیم‌ماهه از ۴۰، سالانه از ۶۰] */
    private const MARKS = [
        'قرآن کریم' => [36, 55], 'دری' => [33, 50], 'پشتو' => [28, 44], 'انگلیسی' => [30, 47], 'ریاضی' => [27, 41],
        'فزیک' => [25, 43], 'کیمیا' => [31, 46], 'بیولوژی' => [34, 52], 'تاریخ' => [35, 54], 'جغرافیه' => [32, 49],
    ];

    public static function student(): Student
    {
        $student = new Student([
            'admission_no' => '1001', 'first_name' => 'احمد', 'last_name' => 'نمونه', 'father_name' => 'محمود',
            'grandfather_name' => 'عبدالکریم', 'gender' => 'male',
        ]);
        $student->birth_date = Carbon::parse('2009-03-21');

        return $student;
    }

    /** @return array{0: Section, 1: Collection} */
    public static function reportCard(): array
    {
        $section = new Section(['name' => 'الف', 'shift' => 'morning']);
        $section->setRelation('gradeLevel', new GradeLevel(['name' => 'صنف دهم']));
        $section->setRelation('academicYear', new AcademicYear(['name' => '1405']));
        $section->setRelation('classTeacher', new Staff(['first_name' => 'حمیدالله', 'last_name' => 'نمونه']));

        $enrollment = new Enrollment(['roll_no' => 7]);
        $enrollment->setRelation('student', self::student());

        $calculator = ResultCalculator::fromSettings();
        $exams = [['id' => 1, 'name' => 'چهارونیم‌ماهه', 'max_mark' => 40], ['id' => 2, 'name' => 'سالانه', 'max_mark' => 60]];
        $subjects = [];
        foreach (self::MARKS as $name => [$mid, $final]) {
            $sum = $mid + $final;
            $subjects[] = [
                'name' => $name,
                'marks' => [1 => ['mark' => $mid, 'absent' => false], 2 => ['mark' => $final, 'absent' => false]],
                'final' => (float) $sum,
                'passed' => $sum >= $calculator->passPercent,
            ];
        }
        $total = array_sum(array_column($subjects, 'final'));
        $average = round($total / count($subjects), 2);
        $result = FinalResult::Passed;

        return [$section, collect([[
            'enrollment' => $enrollment,
            'exams' => $exams,
            'subjects' => $subjects,
            'total' => $total,
            'average' => $average,
            'result' => $result->value,
            'grade' => $calculator->grade($result, $average),
            'provisional' => false,
            'settings' => $calculator->settings(),
            'attendance' => ['present' => 168, 'late' => 4, 'absent' => 3, 'excused' => 2, 'sick' => 1],
            'reexam' => null,
        ]])];
    }

    /** @return array{student: Student, data: array} */
    public static function transcript(Collection $levels): array
    {
        if ($levels->isEmpty()) {
            $levels = collect(['صنف دهم', 'صنف یازدهم', 'صنف دوازدهم'])->map(fn ($n) => new GradeLevel(['name' => $n]));
        }
        $calculator = ResultCalculator::fromSettings();
        $names = array_keys(self::MARKS);
        $columns = [];
        foreach ($levels->values() as $i => $level) {
            $subjects = [];
            foreach (self::MARKS as $name => [$mid, $final]) {
                $mark = min(100, $mid + $final + ($i * 2) - 1);
                $subjects[] = ['name' => $name, 'mark' => (float) $mark, 'passed' => $mark >= $calculator->passPercent];
            }
            $total = array_sum(array_column($subjects, 'mark'));
            $average = round($total / count($subjects), 2);
            $columns[] = [
                'level' => $level, 'source' => $i === 0 ? 'manual' : 'system', 'year' => (string) (1403 + $i), 'school' => null,
                'subjects' => $subjects, 'total' => (float) $total, 'average' => $average, 'result' => 'passed',
                'grade' => $calculator->grade(FinalResult::Passed, $average),
            ];
        }
        $rows = array_map(fn ($name) => [
            'name' => $name,
            'marks' => array_map(fn ($c) => collect($c['subjects'])->firstWhere('name', $name)['mark'], $columns),
            'passed' => array_map(fn ($c) => true, $columns),
        ], $names);

        return ['student' => self::student(), 'data' => [
            'columns' => $columns,
            'rows' => $rows,
            'total' => round(array_sum(array_column($columns, 'total')), 2),
            'average' => round(array_sum(array_column($columns, 'average')) / count($columns), 2),
            'complete' => true,
            'result' => 'passed',
        ]];
    }
}
