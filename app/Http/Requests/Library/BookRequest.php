<?php

namespace App\Http\Requests\Library;

use App\Enums\BookCategory;
use App\Enums\BookLanguage;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\Book;
use App\Services\FinanceService;
use App\Services\LibraryService;
use App\Support\Digits;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ثبت و ویرایش عنوان کتاب (مرحله‌ی ۱۷؛ مسیر با can:library.manage بسته است).
 * هنگام ثبت: شمار جلد، نمبر ثبت اول و مشخصات جلدها (منبع، حالت، تاریخ رسیدن، قیمت) هم می‌آید.
 */
class BookRequest extends FormRequest
{
    use ConvertsJalaliDates;
    use CopyRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function jalaliDateFields(): array
    {
        return ['acquired_on'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['publish_year', 'pages', 'copies', 'isbn'])->filter(fn ($k) => $this->filled($k))
            ->mapWithKeys(fn ($k) => [$k => Digits::toLatin((string) $this->input($k))])->all());
    }

    public function rules(): array
    {
        $book = [
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:150'],
            'translator' => ['nullable', 'string', 'max:150'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'edition' => ['nullable', 'string', 'max:50'],
            'isbn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9Xx\-\s]+$/'],
            'category' => ['required', Rule::enum(BookCategory::class)],
            'language' => ['required', Rule::enum(BookLanguage::class)],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'call_number' => ['nullable', 'string', 'max:30'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'keywords' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_reference' => ['nullable', 'boolean'],
        ];

        return $this->isMethod('POST')
            ? array_merge($book, ['copies' => ['required', 'integer', 'min:0', 'max:'.LibraryService::MAX_COPIES], 'start_accession' => ['nullable', 'string', 'max:30']], $this->copyRules())
            : $book;
    }

    public function attributes(): array
    {
        return __('library.attributes');
    }

    /** @return array<string, mixed> خانه‌های کتاب */
    public function bookData(): array
    {
        $data = collect($this->validated())->only(Book::FIELDS)->all();
        $data['is_reference'] = $this->boolean('is_reference');
        $data['isbn'] = isset($data['isbn']) ? strtoupper(preg_replace('/\s+/', '', $data['isbn'])) : null;

        return $data;
    }

    /** @return array<string, mixed> خانه‌های جلد */
    public function copyData(): array
    {
        return self::copyFields($this->validated());
    }

    /** @param array<string, mixed> $v */
    public static function copyFields(array $v): array
    {
        $cents = FinanceService::parseAmount($v['price'] ?? null);

        return [
            'source' => $v['source'] ?? 'purchase',
            'condition' => $v['condition'] ?? 'new',
            'acquired_on' => $v['acquired_on'] ?? null,
            'price' => $cents !== null ? Money::fromCents($cents) : null,
            'notes' => $v['copy_notes'] ?? null,
        ];
    }
}
