<?php

namespace App\Services;

use App\Enums\BorrowerType;
use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\ModelChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * کتابخانه (مرحله‌ی ۱۷). همه‌ی تغییرها از این‌جا (transaction + قفل جلد + ثبت رویداد).
 * - عنوان (Book) و جلدها (BookCopy) با نمبر ثبت یکتا؛ جلد حذف نمی‌شود (خراب/گم/خارج از استفاده).
 * - امانت عادی با مدت و حد امانت از تنظیمات؛ تمدید؛ بازگشت با حالت جلد؛ گم شدن.
 * - توزیع کتاب درسی به تمام شاگردان یک بخش و جمع‌آوری آن در آخر سال.
 */
class LibraryService
{
    /** بیشترین جلد در یک بار افزودن */
    public const MAX_COPIES = 200;

    /** کلیدهای قاعده‌ی امانت در settings (پیش‌فرض: config/school.php) */
    public const RULE_KEYS = [
        'library_loan_days_student', 'library_loan_days_staff', 'library_max_student', 'library_max_staff', 'library_max_renewals', 'library_block_overdue', 'library_name',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    /** @return array{days_student: int, days_staff: int, max_student: int, max_staff: int, max_renewals: int, block_overdue: bool} */
    public static function rules(): array
    {
        return [
            'days_student' => max(1, (int) Setting::get('library_loan_days_student', 14)),
            'days_staff' => max(1, (int) Setting::get('library_loan_days_staff', 30)),
            'max_student' => max(1, (int) Setting::get('library_max_student', 2)),
            'max_staff' => max(1, (int) Setting::get('library_max_staff', 5)),
            'max_renewals' => max(0, (int) Setting::get('library_max_renewals', 1)),
            'block_overdue' => Setting::enabled('library_block_overdue'),
        ];
    }

    // ------------------------------------------------------------------ فهرست و جلدها

    /**
     * @param  array<string, mixed>  $data  خانه‌های کتاب
     * @param  array<string, mixed>  $copyData  source، condition، acquired_on، price
     */
    public function createBook(array $data, int $copies, ?string $startAccession, array $copyData, User $user): Book
    {
        return DB::transaction(function () use ($data, $copies, $startAccession, $copyData, $user) {
            $book = new Book($data);
            $book->created_by = $user->id;
            $book->save();
            $this->logger->log('library.book_created', $book, $book->display(), user: $user);
            if ($copies > 0) {
                $this->addCopies($book, $copies, $startAccession, $copyData, $user);
            }

            return $book;
        });
    }

    public function updateBook(Book $book, array $data, User $user): void
    {
        DB::transaction(function () use ($book, $data, $user) {
            $book->fill($data);
            [$old, $new] = ModelChanges::capture($book);
            $book->save();
            if ($new !== []) {
                $this->logger->log('library.book_updated', $book, $book->display(), $old, $new, $user);
            }
        });
    }

    /** فقط عنوانی که هیچ جلدی ندارد (ثبت اشتباه) */
    public function deleteBook(Book $book, User $user): void
    {
        if ($book->copies()->exists()) {
            throw ValidationException::withMessages(['book' => __('library.cannot_delete_book')]);
        }
        DB::transaction(function () use ($book, $user) {
            $this->logger->log('library.book_deleted', null, $book->display(), user: $user);
            $book->delete();
        });
    }

    /** نمبر ثبت پیشنهادی بعدی: بزرگ‌ترین نمبر عددی + ۱ */
    public function nextAccession(): string
    {
        $max = (int) BookCopy::query()->whereRaw("accession_no REGEXP '^[0-9]+$'")->max(DB::raw('CAST(accession_no AS UNSIGNED)'));

        return (string) ($max + 1);
    }

    /**
     * افزودن چند جلد با نمبر ثبت پشت سر هم (از start یا نمبر بعدی). «ب-۱۲۰» ← ب-۱۲۰، ب-۱۲۱، …
     *
     * @return Collection<int, BookCopy>
     */
    public function addCopies(Book $book, int $count, ?string $start, array $copyData, User $user): Collection
    {
        if ($count < 1 || $count > self::MAX_COPIES) {
            throw ValidationException::withMessages(['copies' => __('library.copies_range', ['max' => Digits::toPersian((string) self::MAX_COPIES)])]);
        }
        $numbers = $this->accessionRange(trim(Digits::toLatin((string) $start)) ?: $this->nextAccession(), $count);

        try {
            return DB::transaction(function () use ($book, $numbers, $copyData, $user) {
                $taken = BookCopy::whereIn('accession_no', $numbers)->lockForUpdate()->pluck('accession_no');
                if ($taken->isNotEmpty()) {
                    throw ValidationException::withMessages(['start_accession' => __('library.accession_taken', ['numbers' => Digits::toPersian($taken->take(5)->implode('، '))])]);
                }
                $created = collect();
                foreach ($numbers as $no) {
                    $copy = new BookCopy($copyData);
                    $copy->forceFill(['book_id' => $book->id, 'accession_no' => $no, 'status' => CopyStatus::Available, 'created_by' => $user->id])->save();
                    $created->push($copy);
                }
                $this->logger->log('library.copies_added', $book, $book->display(), new: [
                    'count' => count($numbers), 'from' => $numbers[0], 'to' => end($numbers),
                ], user: $user);

                return $created;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['start_accession' => __('library.accession_taken', ['numbers' => ''])]);
        }
    }

    /** @return list<string> */
    private function accessionRange(string $start, int $count): array
    {
        if (mb_strlen($start) > 30) {
            throw ValidationException::withMessages(['start_accession' => __('library.accession_invalid')]);
        }
        if (! preg_match('/^(.*?)(\d+)$/u', $start, $m)) {
            if ($count === 1) {
                return [$start];
            }
            throw ValidationException::withMessages(['start_accession' => __('library.accession_needs_number')]);
        }
        [$prefix, $digits] = [$m[1], $m[2]];
        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $list[] = $prefix.str_pad((string) ((int) $digits + $i), strlen($digits), '0', STR_PAD_LEFT);
        }

        return $list;
    }

    /** @param  array<string, mixed>  $data  accession_no، condition، source، acquired_on، price، notes */
    public function updateCopy(BookCopy $copy, array $data, User $user): void
    {
        $no = trim(Digits::toLatin((string) ($data['accession_no'] ?? $copy->accession_no)));
        if ($no !== $copy->accession_no && BookCopy::where('accession_no', $no)->exists()) {
            throw ValidationException::withMessages(['accession_no' => __('library.accession_taken', ['numbers' => Digits::toPersian($no)])]);
        }
        DB::transaction(function () use ($copy, $data, $no, $user) {
            $copy->fill(collect($data)->except('accession_no')->all());
            $copy->accession_no = $no;
            [$old, $new] = ModelChanges::capture($copy);
            $copy->save();
            if ($new !== []) {
                $this->logger->log('library.copy_updated', $copy->book, $this->copyLabel($copy), $old, $new, $user);
            }
        });
    }

    /** خراب، گم، خارج از استفاده یا برگشت به «موجود» (مثلاً جلد گم پیدا شد یا ترمیم شد) */
    public function setCopyStatus(BookCopy $copy, CopyStatus $status, ?string $reason, User $user): void
    {
        if ($status === CopyStatus::OnLoan) {
            throw ValidationException::withMessages(['status' => __('library.status_not_allowed')]);
        }
        DB::transaction(function () use ($copy, $status, $reason, $user) {
            $copy = BookCopy::whereKey($copy->id)->lockForUpdate()->firstOrFail();
            if ($copy->status === CopyStatus::OnLoan) {
                throw ValidationException::withMessages(['status' => __('library.copy_on_loan')]);
            }
            if ($status !== CopyStatus::Available && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => __('library.reason_required')]);
            }
            $old = $copy->status;
            $copy->forceFill([
                'status' => $status,
                'status_reason' => $status === CopyStatus::Available ? null : mb_substr(trim((string) $reason), 0, 300),
                'status_changed_on' => AttendanceService::today()->toDateString(),
            ])->save();
            $this->logger->log('library.copy_status', $copy->book, $this->copyLabel($copy).' — '.$reason, ['status' => $old->value], ['status' => $status->value], $user);
        });
    }

    // ------------------------------------------------------------------ امانت

    /** جلد با نمبر ثبت (ارقام فارسی هم) */
    public function findCopy(string $accession): ?BookCopy
    {
        return BookCopy::with('book')->where('accession_no', trim(Digits::toLatin($accession)))->first();
    }

    /** امانت‌های فعال امانت‌گیرنده */
    public function activeLoansOf(Student|Staff $borrower): Collection
    {
        return $this->borrowerQuery($borrower)->active()->with('copy.book')->orderBy('due_on')->get();
    }

    public function issue(BookCopy $copy, Student|Staff $borrower, ?string $dueOn, User $user, ?string $notes = null): BookLoan
    {
        $this->ensureBorrowerActive($borrower);
        $rules = self::rules();
        $isStudent = $borrower instanceof Student;
        $today = AttendanceService::today();

        if ($rules['block_overdue'] && $this->borrowerQuery($borrower)->overdue()->exists()) {
            throw ValidationException::withMessages(['accession_no' => __('library.has_overdue')]);
        }
        $max = $isStudent ? $rules['max_student'] : $rules['max_staff'];
        if ($this->borrowerQuery($borrower)->active()->where('kind', LoanKind::Loan->value)->count() >= $max) {
            throw ValidationException::withMessages(['accession_no' => __('library.limit_reached', ['max' => Digits::toPersian((string) $max)])]);
        }
        $due = $dueOn ?: $today->addDays($isStudent ? $rules['days_student'] : $rules['days_staff'])->toDateString();
        if ($due < $today->toDateString()) {
            throw ValidationException::withMessages(['due_on' => __('library.due_in_past')]);
        }

        return DB::transaction(fn () => $this->createLoan($copy, $borrower, LoanKind::Loan, $due, $user, $notes));
    }

    public function renew(BookLoan $loan, User $user): void
    {
        $rules = self::rules();
        if ($loan->status !== LoanStatus::Active || $loan->kind !== LoanKind::Loan) {
            throw ValidationException::withMessages(['loan' => __('library.cannot_renew')]);
        }
        if ($loan->renewals >= $rules['max_renewals']) {
            throw ValidationException::withMessages(['loan' => __('library.renew_limit', ['max' => Digits::toPersian((string) $rules['max_renewals'])])]);
        }
        DB::transaction(function () use ($loan, $rules, $user) {
            $days = $loan->borrower_type === BorrowerType::Student ? $rules['days_student'] : $rules['days_staff'];
            $old = $loan->due_on->toDateString();
            $new = AttendanceService::today()->addDays($days)->toDateString();
            $loan->forceFill(['due_on' => max($new, $old), 'renewals' => $loan->renewals + 1])->save();
            $this->logger->log('library.renewed', $loan->copy->book, $this->loanLabel($loan), ['due_on' => $old], ['due_on' => $loan->due_on->toDateString()], $user);
        });
    }

    /** @param  ?string  $condition  new|good|fair|poor|damaged */
    public function returnLoan(BookLoan $loan, ?string $condition, ?string $note, User $user, ?string $date = null): void
    {
        DB::transaction(function () use ($loan, $condition, $note, $user, $date) {
            $loan = BookLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($loan->status !== LoanStatus::Active) {
                throw ValidationException::withMessages(['loan' => __('library.not_active')]);
            }
            $copy = BookCopy::whereKey($loan->book_copy_id)->lockForUpdate()->firstOrFail();
            $loan->forceFill([
                'status' => LoanStatus::Returned,
                'returned_on' => $date ?: AttendanceService::today()->toDateString(),
                'return_condition' => $condition ?: null,
                'notes' => $this->joinNote($loan->notes, $note),
                'closed_by' => $user->id,
            ])->save();

            $damaged = $condition === 'damaged';
            $copy->forceFill([
                'status' => $damaged ? CopyStatus::Damaged : CopyStatus::Available,
                'condition' => ! $damaged && $condition && CopyCondition::tryFrom($condition) ? $condition : $copy->condition,
                'status_reason' => $damaged ? mb_substr(trim((string) $note) ?: __('library.damaged_on_return'), 0, 300) : null,
                'status_changed_on' => $damaged ? AttendanceService::today()->toDateString() : $copy->status_changed_on,
            ])->save();
            $loan->setRelation('copy', $copy);
            $this->logger->log('library.returned', $copy->book, $this->loanLabel($loan), new: array_filter([
                'condition' => $condition, 'overdue_days' => $loan->overdueDays() ?: null,
            ]), user: $user);
        });
    }

    public function markLost(BookLoan $loan, string $note, User $user): void
    {
        DB::transaction(function () use ($loan, $note, $user) {
            $loan = BookLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($loan->status !== LoanStatus::Active) {
                throw ValidationException::withMessages(['loan' => __('library.not_active')]);
            }
            $copy = BookCopy::whereKey($loan->book_copy_id)->lockForUpdate()->firstOrFail();
            $loan->forceFill(['status' => LoanStatus::Lost, 'returned_on' => AttendanceService::today()->toDateString(), 'notes' => $this->joinNote($loan->notes, $note), 'closed_by' => $user->id])->save();
            $copy->forceFill(['status' => CopyStatus::Lost, 'status_reason' => mb_substr(__('library.lost_by', ['name' => $loan->borrowerName()]).' — '.$note, 0, 300), 'status_changed_on' => AttendanceService::today()->toDateString()])->save();
            $loan->setRelation('copy', $copy);
            $this->logger->log('library.lost', $copy->book, $this->loanLabel($loan).' — '.$note, user: $user);
        });
    }

    // ------------------------------------------------------------------ کتاب درسی

    /**
     * یک جلد از این کتاب برای هر شاگرد فعال بخش که هنوز ندارد (کتاب درسی تا آخر سال).
     *
     * @return array{issued: int, already: int, missing: int}
     */
    public function distributeTextbooks(Section $section, Book $book, string $dueOn, User $user): array
    {
        if ($book->is_reference) {
            throw ValidationException::withMessages(['book' => __('library.reference_not_lendable')]);
        }

        return DB::transaction(function () use ($section, $book, $dueOn, $user) {
            $enrollments = Enrollment::with('student')->where('section_id', $section->id)->where('status', EnrollmentStatus::Active)
                ->orderBy('roll_no')->orderBy('id')->get();
            $have = BookLoan::active()->whereIn('student_id', $enrollments->pluck('student_id'))
                ->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->pluck('student_id')->flip();
            $copies = BookCopy::where('book_id', $book->id)->where('status', CopyStatus::Available)->orderBy('id')->lockForUpdate()->get();

            $result = ['issued' => 0, 'already' => 0, 'missing' => 0];
            foreach ($enrollments as $enrollment) {
                if ($have->has($enrollment->student_id)) {
                    $result['already']++;

                    continue;
                }
                $copy = $copies->shift();
                if (! $copy) {
                    $result['missing']++;

                    continue;
                }
                $this->createLoan($copy, $enrollment->student, LoanKind::Textbook, $dueOn, $user, null, log: false);
                $result['issued']++;
            }
            $this->logger->log('library.textbooks_distributed', $book, $book->display().' — '.$section->label, new: $result, user: $user);

            return $result;
        });
    }

    /**
     * جمع‌آوری (بازگشت) گروهی امانت‌ها، مثلاً کتاب‌های درسی آخر سال.
     *
     * @param  list<int>  $loanIds
     */
    public function collect(array $loanIds, ?string $condition, User $user): int
    {
        return DB::transaction(function () use ($loanIds, $condition, $user) {
            $count = 0;
            foreach (BookLoan::active()->whereIn('id', $loanIds)->get() as $loan) {
                $this->returnLoan($loan, $condition, null, $user);
                $count++;
            }

            return $count;
        });
    }

    // ------------------------------------------------------------------ کمکی

    private function createLoan(BookCopy $copy, Student|Staff $borrower, LoanKind $kind, string $due, User $user, ?string $notes, bool $log = true): BookLoan
    {
        $copy = BookCopy::with('book')->whereKey($copy->id)->lockForUpdate()->firstOrFail();
        if ($copy->status !== CopyStatus::Available) {
            throw ValidationException::withMessages(['accession_no' => __('library.copy_not_available', ['no' => Digits::toPersian($copy->accession_no), 'status' => $copy->status->label()])]);
        }
        if ($kind === LoanKind::Loan && $copy->book->is_reference) {
            throw ValidationException::withMessages(['accession_no' => __('library.reference_not_lendable')]);
        }
        $isStudent = $borrower instanceof Student;
        $loan = new BookLoan;
        $loan->forceFill([
            'book_copy_id' => $copy->id,
            'borrower_type' => $isStudent ? BorrowerType::Student : BorrowerType::Staff,
            'student_id' => $isStudent ? $borrower->id : null,
            'staff_id' => $isStudent ? null : $borrower->id,
            'academic_year_id' => AcademicYear::current()?->id,
            'kind' => $kind,
            'loaned_on' => AttendanceService::today()->toDateString(),
            'due_on' => $due,
            'status' => LoanStatus::Active,
            'notes' => ($notes = trim((string) $notes)) !== '' ? mb_substr($notes, 0, 300) : null,
            'issued_by' => $user->id,
        ])->save();
        $copy->forceFill(['status' => CopyStatus::OnLoan])->save();
        $loan->setRelation('copy', $copy);
        if ($log) {
            $this->logger->log('library.issued', $copy->book, $this->loanLabel($loan, $borrower), new: ['due_on' => $due], user: $user);
        }

        return $loan;
    }

    private function ensureBorrowerActive(Student|Staff $borrower): void
    {
        $active = $borrower instanceof Student
            ? ! $borrower->trashed() && $borrower->status === StudentStatus::Active
            : ! $borrower->trashed() && $borrower->employment_status !== EmploymentStatus::Left;
        if (! $active) {
            throw ValidationException::withMessages(['accession_no' => __('library.borrower_inactive')]);
        }
    }

    /** @return Builder<BookLoan> */
    private function borrowerQuery(Student|Staff $borrower)
    {
        return $borrower instanceof Student ? BookLoan::where('student_id', $borrower->id) : BookLoan::where('staff_id', $borrower->id);
    }

    private function joinNote(?string $old, ?string $note): ?string
    {
        $note = trim((string) $note);
        $text = trim(implode(' — ', array_filter([(string) $old, $note])));

        return $text !== '' ? mb_substr($text, 0, 300) : null;
    }

    private function copyLabel(BookCopy $copy): string
    {
        return __('library.accession_short').' '.$copy->accession_no.' — '.$copy->book->title;
    }

    private function loanLabel(BookLoan $loan, Student|Staff|null $borrower = null): string
    {
        return $this->copyLabel($loan->copy).' ← '.($borrower?->full_name ?? $loan->borrowerName());
    }

    /** برای نمایش تاریخ پیش‌فرض بازگشت در فرم */
    public static function defaultDue(BorrowerType $type): string
    {
        $rules = self::rules();

        return JalaliDate::format(AttendanceService::today()->addDays($type === BorrowerType::Student ? $rules['days_student'] : $rules['days_staff']));
    }
}
