<?php

namespace App\Models;

use App\Enums\BookCategory;
use App\Enums\BookLanguage;
use App\Enums\CopyStatus;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** عنوان کتاب در فهرست کتابخانه (مرحله‌ی ۱۷). جلدها در book_copies. فقط از راه LibraryService. */
#[Fillable(Book::FIELDS)]
class Book extends Model
{
    /** خانه‌های فرم کتاب */
    public const FIELDS = ['title', 'author', 'translator', 'publisher', 'publish_year', 'edition', 'isbn', 'category', 'language', 'subject_id',
        'grade_level_id', 'call_number', 'pages', 'keywords', 'notes', 'is_reference'];

    protected function casts(): array
    {
        return ['category' => BookCategory::class, 'language' => BookLanguage::class, 'is_reference' => 'boolean'];
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class)->orderBy('id');
    }

    public function loans(): HasManyThrough
    {
        return $this->hasManyThrough(BookLoan::class, BookCopy::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** شمار جلدها: total (بی جلدهای خارج‌شده)، available، on_loan */
    public function scopeWithCopyCounts(Builder $query): Builder
    {
        return $query->withCount([
            'copies as copies_total' => fn ($q) => $q->where('status', '!=', CopyStatus::Withdrawn->value),
            'copies as copies_available' => fn ($q) => $q->where('status', CopyStatus::Available->value),
            'copies as copies_on_loan' => fn ($q) => $q->where('status', CopyStatus::OnLoan->value),
        ]);
    }

    /** جست‌وجو در عنوان، نویسنده، ISBN، کلیدواژه و نمبر ثبت جلدها */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return PersianText::whereMatches($query, ['title', 'author', 'translator', 'isbn', 'keywords', 'publisher', 'call_number'], $term,
            extra: fn (Builder $q, string $word) => $q->orWhereHas('copies', fn ($c) => $c->where('accession_no', $word)));
    }

    /** «عنوان — نویسنده» برای فهرست‌ها */
    public function display(): string
    {
        return $this->title.($this->author ? ' — '.$this->author : '');
    }
}
