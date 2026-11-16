<?php

namespace App\Services;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Exceptions\ImportFileException;
use App\Http\Requests\Students\StudentRequest;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Rules\ValidJalaliDate;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\PersianText;
use App\Support\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ورود گروهی دانش‌آموزان و کارکنان از Excel (یا CSV) هنگام راه‌اندازی سیستم.
 * گام ۱: خواندن و بررسی هر سطر (بدون ذخیره). گام ۲: ذخیره‌ی سطرهای درست از راه StudentService/StaffService
 * (همان قاعده‌ها و ثبت رویداد ثبت تکی). سطر خطادار یا تکراری وارد نمی‌شود.
 */
class ImportService
{
    public const TYPES = ['students', 'staff'];

    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly StudentService $students,
        private readonly StaffService $staff,
        private readonly EnrollmentService $enrollments,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * ستون‌های هر نوع: کلید => [برچسب سربرگ، اجباری، مثال، راهنما]
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    public function columns(string $type): array
    {
        return __('import.columns.'.$type);
    }

    /** فایل نمونه: برگه‌ی خالی با سربرگ + برگه‌ی راهنما با مثال */
    public function template(string $type): string
    {
        $columns = $this->columns($type);
        $headers = array_map(fn ($c) => $c[0].($c[1] ? ' *' : ''), array_values($columns));

        $guide = [[__('import.guide_column'), __('import.guide_required'), __('import.guide_example'), __('import.guide_hint')]];
        foreach ($columns as $c) {
            $guide[] = [$c[0], $c[1] ? __('app.yes') : '', $c[2], $c[3]];
        }
        $guide[] = [];
        foreach (__('import.guide_notes') as $note) {
            $guide[] = [$note];
        }

        return Xlsx::build([
            ['name' => __('import.sheet_'.$type), 'rows' => [$headers], 'widths' => array_fill(0, count($headers), 20)],
            ['name' => __('import.sheet_guide'), 'rows' => $guide, 'widths' => [28, 10, 26, 70]],
        ]);
    }

    /**
     * خواندن فایل و بررسی همه‌ی سطرها.
     *
     * @return array{rows: list<array{line: int, values: array<string, string>, data: array, status: string, errors: list<string>}>, unknown: list<string>, missing: list<string>}
     */
    public function preview(string $type, string $path, string $extension): array
    {
        $raw = $extension === 'csv' ? $this->readCsv($path) : Xlsx::read($path);
        $blank = fn (array $r) => implode('', array_map('trim', $r)) === '';

        // سربرگ = اولین سطر غیرخالی؛ شماره‌ی سطر (line) همان شماره‌ی سطر در Excel است
        $headerIndex = null;
        foreach ($raw as $i => $r) {
            if (! $blank($r)) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new ImportFileException(__('import.empty_file'));
        }

        [$map, $unknown, $missing] = $this->mapHeaders($type, $raw[$headerIndex]);
        if ($missing !== []) {
            return ['rows' => [], 'unknown' => $unknown, 'missing' => $missing];
        }
        $dataRows = array_filter(array_slice($raw, $headerIndex + 1, preserve_keys: true), fn ($r) => ! $blank($r));
        if (count($dataRows) > self::MAX_ROWS) {
            throw new ImportFileException(__('import.too_many_rows', ['max' => Digits::toPersian((string) self::MAX_ROWS)]));
        }

        $rows = [];
        $seen = [];
        foreach ($dataRows as $i => $cells) {
            $values = [];
            foreach ($map as $index => $key) {
                $values[$key] = $this->clean($key, (string) ($cells[$index] ?? ''));
            }
            $rows[] = $type === 'students'
                ? $this->checkStudent($values, $i + 1, $seen)
                : $this->checkStaff($values, $i + 1, $seen);
        }

        return ['rows' => $rows, 'unknown' => $unknown, 'missing' => []];
    }

    /**
     * ذخیره‌ی سطرهای درست.
     *
     * @return array{imported: int, enrolled: int, skipped: int}
     */
    public function import(string $type, array $rows, User $user): array
    {
        $ok = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ok'));

        return DB::transaction(function () use ($type, $ok, $rows, $user) {
            $enrolled = 0;
            foreach ($ok as $row) {
                if ($type === 'students') {
                    $student = $this->students->create($row['data']['student'], null, $row['data']['guardian']);
                    if ($row['data']['section_id']) {
                        $this->enrollments->enroll($student, Section::findOrFail($row['data']['section_id']));
                        $enrolled++;
                    }
                } else {
                    $this->staff->create($row['data']);
                }
            }

            $summary = ['imported' => count($ok), 'enrolled' => $enrolled, 'skipped' => count($rows) - count($ok)];
            $this->logger->log('import.'.$type, null, __('import.type_'.$type), new: $summary, user: $user);

            return $summary;
        });
    }

    // ------------------------------------------------------------------ بررسی سطرها

    private function checkStudent(array $v, int $line, array &$seen): array
    {
        $errors = [];
        $gender = $this->gender($v['gender'] ?? '');
        if (($v['gender'] ?? '') !== '' && ! $gender) {
            $errors[] = __('import.bad_gender_student');
        }

        $data = [
            'admission_no' => $v['admission_no'] ?? '',
            'first_name' => $v['first_name'] ?? '',
            'last_name' => ($v['last_name'] ?? '') ?: null,
            'father_name' => $v['father_name'] ?? '',
            'grandfather_name' => ($v['grandfather_name'] ?? '') ?: null,
            'gender' => $gender?->value ?? ($v['gender'] ?? ''),
            'birth_date' => ($v['birth_date'] ?? '') ?: null,
            'tazkira_no' => ($v['tazkira_no'] ?? '') ?: null,
            'phone' => ($v['phone'] ?? '') ?: null,
            'province' => ($v['province'] ?? '') ?: null,
            'district' => ($v['district'] ?? '') ?: null,
            'village' => ($v['village'] ?? '') ?: null,
            'address' => ($v['address'] ?? '') ?: null,
            'admission_date' => ($v['admission_date'] ?? '') ?: null,
        ];

        $validator = Validator::make($data + [
            'guardian_name' => $v['guardian_name'] ?? '', 'guardian_relation' => $v['guardian_relation'] ?? '', 'guardian_phone' => $v['guardian_phone'] ?? '',
        ], [
            'admission_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('students', 'admission_no')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'tazkira_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'province' => ['nullable', Rule::in(config('afghanistan.provinces'))],
            'district' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'admission_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_relation' => ['nullable', 'required_with:guardian_name', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
        ], [
            'admission_no.unique' => __('import.admission_no_taken'),
            'admission_no.regex' => __('students.admission_no_format'),
            'phone.regex' => __('validation.phone'),
            'guardian_phone.regex' => __('validation.phone'),
            'gender.required' => __('import.bad_gender_student'),
            'gender.enum' => __('import.bad_gender_student'),
        ], __('import.attributes'));
        $errors = array_values(array_unique(array_merge($errors, $validator->errors()->all())));

        // نمبر اساس تکراری در خود فایل
        if ($data['admission_no'] !== '') {
            if (isset($seen['no'][$data['admission_no']])) {
                $errors[] = __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['no'][$data['admission_no']])]);
            }
            $seen['no'][$data['admission_no']] = $line;
        }

        // صنف و بخش
        $sectionId = null;
        $sectionLabel = '';
        if (($v['grade'] ?? '') !== '' || ($v['section'] ?? '') !== '') {
            [$section, $error] = $this->findSection($v['grade'] ?? '', $v['section'] ?? '');
            if ($error) {
                $errors[] = $error;
            } else {
                $sectionId = $section->id;
                $sectionLabel = $section->label;
            }
        }

        $status = $errors === [] ? 'ok' : 'error';
        if ($status === 'ok') {
            $key = PersianText::normalize($data['first_name'].'|'.($data['last_name'] ?? '').'|'.$data['father_name']);
            $exists = Student::withTrashed()
                ->where('first_name', $data['first_name'])
                ->where('father_name', $data['father_name'])
                ->where(fn ($q) => $data['last_name'] ? $q->where('last_name', $data['last_name']) : $q->whereNull('last_name')->orWhere('last_name', ''))
                ->exists();
            if ($exists || isset($seen['person'][$key])) {
                $status = 'duplicate';
                $errors[] = $exists ? __('import.duplicate_in_db') : __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['person'][$key])]);
            }
            $seen['person'][$key] ??= $line;
        }

        $guardian = ($v['guardian_name'] ?? '') !== ''
            ? ['name' => $v['guardian_name'], 'relation' => $v['guardian_relation'] ?? '', 'phone' => ($v['guardian_phone'] ?? '') ?: null]
            : null;

        $student = $data;
        $student['birth_date'] = JalaliDate::parse($data['birth_date'])?->toDateString();
        $student['admission_date'] = JalaliDate::parse($data['admission_date'])?->toDateString();

        return [
            'line' => $line,
            'values' => $v + ['section_label' => $sectionLabel],
            'data' => ['student' => $student, 'guardian' => $guardian, 'section_id' => $sectionId],
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function checkStaff(array $v, int $line, array &$seen): array
    {
        $errors = [];
        $type = $this->staffType($v['staff_type'] ?? '');
        $gender = $this->gender($v['gender'] ?? '');
        $education = $this->education($v['education'] ?? '');

        if (($v['staff_type'] ?? '') === '' || ! $type) {
            $errors[] = __('import.bad_staff_type');
        }
        if (! $gender) {
            $errors[] = __('import.bad_gender_staff');
        }
        if (($v['education'] ?? '') !== '' && ! $education) {
            $errors[] = __('import.bad_education');
        }

        $data = [
            'staff_type' => $type?->value,
            'first_name' => $v['first_name'] ?? '',
            'last_name' => ($v['last_name'] ?? '') ?: null,
            'father_name' => ($v['father_name'] ?? '') ?: null,
            'gender' => $gender?->value,
            'phone' => ($v['phone'] ?? '') ?: null,
            'education' => $education?->value,
            'specialization' => ($v['specialization'] ?? '') ?: null,
            'position' => ($v['position'] ?? '') ?: null,
            'hire_date' => ($v['hire_date'] ?? '') ?: null,
            'address' => ($v['address'] ?? '') ?: null,
        ];

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:'.StudentRequest::PHONE_REGEX],
            'specialization' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'hire_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'address' => ['nullable', 'string', 'max:500'],
        ], ['phone.regex' => __('validation.phone')], __('import.attributes'));
        $errors = array_values(array_unique(array_merge($errors, $validator->errors()->all())));

        $status = $errors === [] ? 'ok' : 'error';
        if ($status === 'ok') {
            $key = PersianText::normalize($data['first_name'].'|'.($data['last_name'] ?? '').'|'.($data['father_name'] ?? ''));
            $exists = Staff::withTrashed()
                ->where('first_name', $data['first_name'])
                ->where(fn ($q) => $data['last_name'] ? $q->where('last_name', $data['last_name']) : $q->whereNull('last_name')->orWhere('last_name', ''))
                ->where(fn ($q) => $data['father_name'] ? $q->where('father_name', $data['father_name']) : $q->whereNull('father_name')->orWhere('father_name', ''))
                ->exists();
            if ($exists || isset($seen['person'][$key])) {
                $status = 'duplicate';
                $errors[] = $exists ? __('import.duplicate_in_db') : __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['person'][$key])]);
            }
            $seen['person'][$key] ??= $line;
        }

        $data['hire_date'] = JalaliDate::parse($data['hire_date'])?->toDateString();

        return ['line' => $line, 'values' => $v, 'data' => $data, 'status' => $status, 'errors' => $errors];
    }

    // ------------------------------------------------------------------ کمکی‌ها

    /** @return array{0: array<int, string>, 1: list<string>, 2: list<string>} [شماره‌ی ستون => کلید، ستون‌های ناشناخته، ستون‌های اجباری نبود] */
    private function mapHeaders(string $type, array $header): array
    {
        $columns = $this->columns($type);
        $byLabel = [];
        foreach ($columns as $key => $c) {
            $byLabel[$this->headerKey($c[0])] = $key;
        }

        $map = [];
        $unknown = [];
        foreach ($header as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $key = $byLabel[$this->headerKey($label)] ?? null;
            $key ? $map[$i] = $key : $unknown[] = $label;
        }

        $missing = [];
        foreach ($columns as $key => $c) {
            if ($c[1] && ! in_array($key, $map, true)) {
                $missing[] = $c[0];
            }
        }

        return [$map, $unknown, $missing];
    }

    /** «نام پدر *» و «نام  پدر» و «نام پدر (اجباری)» ← یک کلید */
    private function headerKey(string $label): string
    {
        $label = preg_replace('/\(.*?\)|\*/u', '', PersianText::normalize($label));

        return preg_replace('/[\s\x{200C}]+/u', '', (string) $label);
    }

    private function clean(string $key, string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', PersianText::normalize($value)) ?? '');
        if (in_array($key, ['admission_no', 'phone', 'guardian_phone', 'tazkira_no', 'birth_date', 'admission_date', 'hire_date'], true)) {
            $value = Digits::toLatin($value);
        }
        // Excel صفر اول شماره را می‌برد: 799123456 ← 0799123456
        if (in_array($key, ['phone', 'guardian_phone'], true) && preg_match('/^7\d{8}$/', $value)) {
            $value = '0'.$value;
        }

        return $value;
    }

    private function gender(string $value): ?Gender
    {
        return match (true) {
            in_array($value, ['پسر', 'مرد', 'ذکور', 'male', 'Male', 'M', 'm'], true) => Gender::Male,
            in_array($value, ['دختر', 'زن', 'اناث', 'female', 'Female', 'F', 'f'], true) => Gender::Female,
            default => null,
        };
    }

    private function staffType(string $value): ?StaffType
    {
        foreach (StaffType::cases() as $case) {
            if ($value === $case->value || $value === $case->label()) {
                return $case;
            }
        }

        return match (true) {
            $value === 'اداری' || str_contains($value, 'اداری') => StaffType::Admin,
            $value === 'خدماتی' || str_contains($value, 'خدماتی') => StaffType::Service,
            str_contains($value, 'کورس') => StaffType::CourseTeacher,
            str_contains($value, 'معلم') || str_contains($value, 'استاد') => StaffType::Teacher,
            default => null,
        };
    }

    private function education(string $value): ?EducationLevel
    {
        if ($value === '') {
            return null;
        }
        foreach (EducationLevel::cases() as $case) {
            if ($value === $case->value || $value === $case->label() || str_starts_with($case->label(), $value)) {
                return $case;
            }
        }

        return match (true) {
            str_contains($value, 'دوازده') || $value === '12' => EducationLevel::Grade12,
            str_contains($value, 'چهارده') || $value === '14' => EducationLevel::Grade14,
            str_contains($value, 'ارشد') || str_contains($value, 'ماستر') => EducationLevel::Master,
            str_contains($value, 'لیسانس') || str_contains($value, 'کارشناسی') || str_contains($value, 'لسانس') => EducationLevel::Bachelor,
            str_contains($value, 'دکتر') => EducationLevel::Doctorate,
            default => null,
        };
    }

    /** @return array{0: ?Section, 1: ?string} */
    private function findSection(string $grade, string $name): array
    {
        $year = AcademicYear::current();
        if (! $year) {
            return [null, __('import.no_current_year')];
        }

        $g = Digits::toLatin($grade);
        $level = ctype_digit($g)
            ? GradeLevel::where('sort_order', (int) $g)->first()
            : GradeLevel::whereIn('name', [$grade, 'صنف '.$grade])->first();
        if (! $level) {
            return [null, __('import.grade_not_found', ['grade' => $grade])];
        }

        $sections = Section::with('gradeLevel')->where('academic_year_id', $year->id)->where('grade_level_id', $level->id)
            ->when($name !== '', fn ($q) => $q->where('name', $name))
            ->get();

        return match (true) {
            $sections->count() === 1 => [$sections->first(), null],
            $sections->isEmpty() => [null, __('import.section_not_found', ['grade' => $level->name, 'section' => $name ?: '—'])],
            default => [null, __('import.section_ambiguous', ['grade' => $level->name])],
        };
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $delimiter = substr_count(strtok($content, "\n") ?: '', ';') > substr_count(strtok($content, "\n") ?: '', ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }
}
