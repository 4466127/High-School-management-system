<?php

namespace App\Services;

use App\Enums\BookCategory;
use App\Enums\BookLanguage;
use App\Enums\ChemicalHazard;
use App\Enums\CopyCondition;
use App\Enums\CopySource;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\LabItemKind;
use App\Enums\LabUnit;
use App\Enums\StaffType;
use App\Enums\StudentStatus;
use App\Exceptions\ImportFileException;
use App\Http\Requests\Students\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\GradeLevel;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Rules\ValidJalaliDate;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\PersianText;
use App\Support\Quantity;
use App\Support\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ورود گروهی دانش‌آموزان و کارکنان از Excel (یا CSV) هنگام راه‌اندازی سیستم.
 * گام ۱: خواندن و بررسی هر سطر (بدون ذخیره). گام ۲: ذخیره‌ی سطرهای درست از راه StudentService/StaffService
 * (همان قاعده‌ها و ثبت رویداد ثبت تکی). سطر خطادار یا تکراری وارد نمی‌شود.
 * lab_items (مرحله‌ی ۱۸): موجودی لابراتوار با مقدار اول (LabService).
 * books (مرحله‌ی ۱۷): فهرست کتاب‌های کتابخانه با شمار جلد و نمبر ثبت (LibraryService).
 * graduates (مرحله‌ی ۱۴): فارغ‌ها و دانش‌آموزان سابق سال‌های قبل، مستقیم در بایگانی با اوسط صنف‌های ۱۰ تا ۱۲ (PastResultService).
 */
class ImportService
{
    public const TYPES = ['students', 'staff', 'graduates', 'books', 'lab_items'];

    /** ستون اوسط فایل فارغ‌ها ← شماره‌ی صنف (GradeLevel::sort_order) و فاصله تا سال فراغت */
    private const GRADUATE_AVERAGES = ['avg_10' => [10, 2], 'avg_11' => [11, 1], 'avg_12' => [12, 0]];

    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly StudentService $students,
        private readonly StaffService $staff,
        private readonly EnrollmentService $enrollments,
        private readonly ActivityLogger $logger,
        private readonly PastResultService $pastResults,
        private readonly LibraryService $library,
        private readonly LabService $labs,
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
            $rows[] = match ($type) {
                'students' => $this->checkStudent($values, $i + 1, $seen),
                'graduates' => $this->checkGraduate($values, $i + 1, $seen),
                'books' => $this->checkBook($values, $i + 1, $seen),
                'lab_items' => $this->checkLabItem($values, $i + 1, $seen),
                default => $this->checkStaff($values, $i + 1, $seen),
            };
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
                if ($type === 'lab_items') {
                    $this->labs->createItem($row['data']['item'], $row['data']['quantity'], $user);
                } elseif ($type === 'books') {
                    $this->library->createBook($row['data']['book'], $row['data']['copies'], $row['data']['start'], $row['data']['copy'], $user);
                } elseif ($type === 'graduates') {
                    $this->pastResults->createFormerStudent($row['data']['student'], $row['data']['leaving'], $row['data']['results'], $user);
                } elseif ($type === 'students') {
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

    /** فارغ یا دانش‌آموز سابق: همان بررسی دانش‌آموز + وضعیت، سال فراغت، سند و اوسط صنف‌ها */
    private function checkGraduate(array $v, int $line, array &$seen): array
    {
        $row = $this->checkStudent(array_diff_key($v, array_flip(['grade', 'section', 'guardian_name', 'guardian_relation', 'guardian_phone'])), $line, $seen);
        $errors = $row['errors'];

        $status = match (true) {
            ($v['status'] ?? '') === '' || str_contains($v['status'], 'فارغ') => StudentStatus::Graduated,
            str_contains($v['status'], 'منتقل') || str_contains($v['status'], 'انتقال') => StudentStatus::Transferred,
            str_contains($v['status'], 'ترک') => StudentStatus::Dropped,
            default => null,
        };
        if (! $status) {
            $errors[] = __('import.bad_former_status');
        }

        $year = $v['graduation_year'] ?? '';
        if ($year !== '' && ! preg_match('/^1[2-4][0-9]{2}$/', $year)) {
            $errors[] = __('past_results.year_format');
        }
        foreach (['left_on', 'certificate_date'] as $field) {
            if (($v[$field] ?? '') !== '' && ! JalaliDate::parse($v[$field])) {
                $errors[] = __('import.bad_date', ['column' => __('import.columns.graduates.'.$field)[0]]);
            }
        }

        $results = [];
        foreach (self::GRADUATE_AVERAGES as $key => [$number, $back]) {
            $avg = $v[$key] ?? '';
            if ($avg === '') {
                continue;
            }
            if (! is_numeric($avg) || $avg < 0 || $avg > 100) {
                $errors[] = __('import.bad_average', ['column' => __('import.columns.graduates.'.$key)[0]]);

                continue;
            }
            $level = GradeLevel::where('sort_order', $number)->first();
            if ($year === '' || ! $level) {
                $errors[] = $level ? __('import.average_needs_year') : __('import.grade_not_found', ['grade' => $number]);

                continue;
            }
            $results[] = ['grade_level_id' => $level->id, 'year_name' => (string) ((int) $year - $back), 'average' => $avg];
        }

        $errors = array_values(array_unique($errors));
        // تکراری تکراری می‌ماند (وارد نمی‌شود)؛ در غیر آن هر خطا ← سطر خطادار
        if ($row['status'] !== 'duplicate') {
            $row['status'] = $errors === [] ? 'ok' : 'error';
        }
        $row['errors'] = $errors;
        $row['values']['status_label'] = $status?->label() ?? ($v['status'] ?? '');
        $row['data']['leaving'] = [
            'status' => $status?->value,
            'left_on' => JalaliDate::parse($v['left_on'] ?? null)?->toDateString(),
            'reason' => $year !== '' ? __('import.graduated_in', ['year' => Digits::toPersian($year)]) : null,
            'certificate_no' => ($v['certificate_no'] ?? '') ?: null,
            'certificate_date' => JalaliDate::parse($v['certificate_date'] ?? null)?->toDateString(),
        ];
        $row['data']['results'] = $results;
        unset($row['data']['guardian'], $row['data']['section_id']);

        return $row;
    }

    /** یک کتاب: دسته و زبان با نام فارسی؛ صنف و مضمون با نام؛ نمبر ثبت اول اختیاری (خالی = سیستم پشت سر هم می‌دهد) */
    private function checkBook(array $v, int $line, array &$seen): array
    {
        $errors = [];
        $title = mb_substr($v['title'] ?? '', 0, 200);
        if ($title === '') {
            $errors[] = __('import.required', ['column' => __('import.columns.books.title')[0]]);
        }
        $category = $this->enumByLabel(BookCategory::class, $v['category'] ?? '', BookCategory::General);
        $language = $this->enumByLabel(BookLanguage::class, $v['language'] ?? '', BookLanguage::Dari);
        $source = $this->enumByLabel(CopySource::class, $v['source'] ?? '', CopySource::Purchase);
        $condition = $this->enumByLabel(CopyCondition::class, $v['condition'] ?? '', CopyCondition::Good);
        foreach (['category' => $category, 'language' => $language, 'source' => $source, 'condition' => $condition] as $key => $value) {
            if (! $value) {
                $errors[] = __('import.bad_choice', ['column' => __('import.columns.books.'.$key)[0], 'value' => $v[$key]]);
            }
        }
        $grade = ($v['grade'] ?? '') !== '' ? GradeLevel::where('name', $v['grade'])->first() : null;
        if (($v['grade'] ?? '') !== '' && ! $grade) {
            $errors[] = __('import.grade_not_found', ['grade' => $v['grade']]);
        }
        $subject = ($v['subject'] ?? '') !== '' ? Subject::where('name', $v['subject'])->first() : null;
        if (($v['subject'] ?? '') !== '' && ! $subject) {
            $errors[] = __('import.subject_not_found', ['subject' => $v['subject']]);
        }
        foreach (['publish_year' => [1000, 2100], 'pages' => [1, 20000], 'copies' => [0, LibraryService::MAX_COPIES]] as $key => [$min, $max]) {
            if (($v[$key] ?? '') !== '' && (! ctype_digit($v[$key]) || (int) $v[$key] < $min || (int) $v[$key] > $max)) {
                $errors[] = __('import.bad_number', ['column' => __('import.columns.books.'.$key)[0]]);
            }
        }
        $price = ($v['price'] ?? '') !== '' ? FinanceService::parseAmount($v['price']) : null;
        if (($v['price'] ?? '') !== '' && $price === null) {
            $errors[] = __('import.bad_number', ['column' => __('import.columns.books.price')[0]]);
        }
        if (($v['acquired_on'] ?? '') !== '' && ! JalaliDate::parse($v['acquired_on'])) {
            $errors[] = __('import.bad_date', ['column' => __('import.columns.books.acquired_on')[0]]);
        }

        // نمبر ثبت: در پایگاه داده یا در سطرهای بالاتر نباشد
        $copies = ($v['copies'] ?? '') === '' ? 1 : (int) $v['copies'];
        $start = $v['accession_start'] ?? '';
        if ($start !== '' && $copies > 0 && $errors === []) {
            preg_match('/^(.*?)(\d+)$/u', $start, $m);
            $numbers = $m ? array_map(fn ($i) => $m[1].str_pad((string) ((int) $m[2] + $i), strlen($m[2]), '0', STR_PAD_LEFT), range(0, $copies - 1)) : [$start];
            if (! $m && $copies > 1) {
                $errors[] = __('library.accession_needs_number');
            } elseif (BookCopy::whereIn('accession_no', $numbers)->exists()) {
                $errors[] = __('library.accession_taken', ['numbers' => Digits::toPersian($numbers[0])]);
            } elseif ($clash = array_intersect($numbers, array_keys($seen['accession'] ?? []))) {
                $errors[] = __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['accession'][reset($clash)])]);
            } else {
                foreach ($numbers as $no) {
                    $seen['accession'][$no] = $line;
                }
            }
        }

        $status = $errors === [] ? 'ok' : 'error';
        if ($status === 'ok') {
            $key = PersianText::normalize($title.'|'.($v['author'] ?? '').'|'.($v['edition'] ?? ''));
            $exists = Book::where('title', $title)->where(fn ($q) => ($v['author'] ?? '') !== '' ? $q->where('author', $v['author']) : $q->whereNull('author'))
                ->where(fn ($q) => ($v['edition'] ?? '') !== '' ? $q->where('edition', $v['edition']) : $q->whereNull('edition'))->exists();
            if ($exists || isset($seen['book'][$key])) {
                $status = 'duplicate';
                $errors[] = $exists ? __('import.duplicate_book') : __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['book'][$key])]);
            }
            $seen['book'][$key] ??= $line;
        }
        $v['category'] = $category?->label() ?? ($v['category'] ?? '');
        $v['copies'] = Digits::toPersian((string) $copies);

        $nullable = fn (string $k, int $max) => ($v[$k] ?? '') !== '' ? mb_substr($v[$k], 0, $max) : null;

        return ['line' => $line, 'values' => $v, 'status' => $status, 'errors' => $errors, 'data' => [
            'book' => [
                'title' => $title, 'author' => $nullable('author', 150), 'translator' => $nullable('translator', 150), 'publisher' => $nullable('publisher', 150),
                'publish_year' => ($v['publish_year'] ?? '') !== '' ? (int) $v['publish_year'] : null, 'edition' => $nullable('edition', 50),
                'isbn' => ($v['isbn'] ?? '') !== '' ? mb_substr(strtoupper(preg_replace('/\s+/', '', $v['isbn'])), 0, 20) : null,
                'category' => $category?->value, 'language' => $language?->value, 'grade_level_id' => $grade?->id, 'subject_id' => $subject?->id,
                'call_number' => $nullable('call_number', 30), 'pages' => ($v['pages'] ?? '') !== '' ? (int) $v['pages'] : null,
                'notes' => $nullable('notes', 500), 'is_reference' => in_array($v['is_reference'] ?? '', ['بلی', 'بله', 'هو', 'yes', '1'], true),
            ],
            'copies' => $copies,
            'start' => $start !== '' ? $start : null,
            'copy' => [
                'source' => $source?->value, 'condition' => $condition?->value,
                'acquired_on' => JalaliDate::parse($v['acquired_on'] ?? null)?->toDateString(),
                'price' => $price !== null ? Money::fromCents($price) : null,
            ],
        ]];
    }

    /** یک قلم موجودی لابراتوار: لابراتوار با نام؛ نوع، واحد، خطر و منبع با نام فارسی؛ مقدار با اعشار */
    private function checkLabItem(array $v, int $line, array &$seen): array
    {
        $errors = [];
        $name = mb_substr($v['name'] ?? '', 0, 150);
        $lab = ($v['lab'] ?? '') !== '' ? Lab::where('name', $v['lab'])->first() : null;
        if ($name === '') {
            $errors[] = __('import.required', ['column' => __('import.columns.lab_items.name')[0]]);
        }
        if (! $lab) {
            $errors[] = ($v['lab'] ?? '') === '' ? __('import.required', ['column' => __('import.columns.lab_items.lab')[0]]) : __('import.lab_not_found', ['lab' => $v['lab']]);
        }
        $kind = $this->enumByLabel(LabItemKind::class, $v['kind'] ?? '', LabItemKind::Equipment);
        $unit = $this->enumByLabel(LabUnit::class, $v['unit'] ?? '', LabUnit::Piece);
        $hazard = ($v['hazard'] ?? '') === '' ? null : $this->enumByLabel(ChemicalHazard::class, $v['hazard'], ChemicalHazard::None);
        $source = $this->enumByLabel(CopySource::class, $v['source'] ?? '', CopySource::Purchase);
        foreach (['kind' => $kind, 'unit' => $unit, 'source' => $source] + (($v['hazard'] ?? '') !== '' ? ['hazard' => $hazard] : []) as $key => $value) {
            if (! $value) {
                $errors[] = __('import.bad_choice', ['column' => __('import.columns.lab_items.'.$key)[0], 'value' => $v[$key]]);
            }
        }
        $qty = ($v['quantity'] ?? '') === '' ? 0 : Quantity::parse($v['quantity']);
        $min = ($v['min_quantity'] ?? '') === '' ? null : Quantity::parse($v['min_quantity']);
        foreach (['quantity' => $qty, 'min_quantity' => $min] as $key => $value) {
            if (($v[$key] ?? '') !== '' && $value === null) {
                $errors[] = __('import.bad_number', ['column' => __('import.columns.lab_items.'.$key)[0]]);
            }
        }
        $price = ($v['unit_price'] ?? '') !== '' ? FinanceService::parseAmount($v['unit_price']) : null;
        if (($v['unit_price'] ?? '') !== '' && $price === null) {
            $errors[] = __('import.bad_number', ['column' => __('import.columns.lab_items.unit_price')[0]]);
        }
        if (($v['expires_on'] ?? '') !== '' && ! JalaliDate::parse($v['expires_on'])) {
            $errors[] = __('import.bad_date', ['column' => __('import.columns.lab_items.expires_on')[0]]);
        }
        $code = ($v['code'] ?? '') !== '' ? mb_substr($v['code'], 0, 30) : null;
        if ($code !== null && (LabItem::where('code', $code)->exists() || isset($seen['code'][$code]))) {
            $errors[] = __('import.code_taken', ['code' => $code]);
        }

        $status = $errors === [] ? 'ok' : 'error';
        if ($status === 'ok') {
            $key = $lab->id.'|'.PersianText::normalize($name);
            if (LabItem::where('lab_id', $lab->id)->where('name', $name)->exists() || isset($seen['item'][$key])) {
                $status = 'duplicate';
                $errors[] = isset($seen['item'][$key]) ? __('import.duplicate_in_file', ['line' => Digits::toPersian((string) $seen['item'][$key])]) : __('import.duplicate_lab_item');
            }
            $seen['item'][$key] ??= $line;
            if ($code !== null) {
                $seen['code'][$code] = $line;
            }
        }
        $v['kind'] = $kind?->label() ?? ($v['kind'] ?? '');

        return ['line' => $line, 'values' => $v, 'status' => $status, 'errors' => $errors, 'data' => [
            'item' => [
                'lab_id' => $lab?->id, 'name' => $name, 'kind' => $kind?->value, 'unit' => $unit?->value, 'code' => $code,
                'min_quantity' => $min !== null ? Quantity::fromHundredths($min) : null,
                'location' => ($v['location'] ?? '') !== '' ? mb_substr($v['location'], 0, 50) : null,
                'hazard' => $hazard?->value, 'expires_on' => JalaliDate::parse($v['expires_on'] ?? null)?->toDateString(),
                'unit_price' => $price !== null ? Money::fromCents($price) : null, 'source' => $source?->value,
                'specification' => ($v['specification'] ?? '') !== '' ? mb_substr($v['specification'], 0, 300) : null,
                'notes' => ($v['notes'] ?? '') !== '' ? mb_substr($v['notes'], 0, 500) : null, 'is_active' => true,
            ],
            'quantity' => $qty ?? 0,
        ]];
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return ?T خالی = پیش‌فرض؛ نام فارسی یا مقدار انگلیسی؛ ناشناخته = null
     */
    private function enumByLabel(string $enum, string $value, \BackedEnum $default): ?\BackedEnum
    {
        if ($value === '') {
            return $default;
        }
        $norm = fn (string $s) => preg_replace('/[\s\x{200C}]+/u', '', PersianText::normalize($s));
        foreach ($enum::cases() as $case) {
            if ($case->value === strtolower($value) || $norm($case->label()) === $norm($value)) {
                return $case;
            }
        }
        // نام کوتاه: «مرجع» ← «مرجع (قاموس، دایرةالمعارف)»، «تکنالوژی» ← «تکنالوژی، صحت و …»
        if (mb_strlen($norm($value)) >= 3) {
            foreach ($enum::cases() as $case) {
                if (str_starts_with($norm($case->label()), $norm($value))) {
                    return $case;
                }
            }
        }

        return null;
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
        // سربرگ‌های قدیمی (فایل‌هایی که پیش از تغییر نام ستون ساخته شده)
        foreach ((array) __('import.header_aliases') as $old => $key) {
            if (isset($columns[$key])) {
                $byLabel[$this->headerKey($old)] ??= $key;
            }
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
        if (in_array($key, ['admission_no', 'phone', 'guardian_phone', 'tazkira_no', 'birth_date', 'admission_date', 'hire_date',
            'graduation_year', 'left_on', 'certificate_date', 'avg_10', 'avg_11', 'avg_12', 'publish_year', 'pages', 'copies', 'accession_start', 'price', 'acquired_on', 'isbn', 'quantity', 'min_quantity', 'unit_price', 'expires_on', 'code'], true)) {
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
