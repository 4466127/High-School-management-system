<?php

namespace App\Models;

use App\Enums\LetterDirection;
use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Support\Digits;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مکتوب صادره یا وارده. تغییر فقط از راه LetterService (transaction، شماره‌ی پشت‌سرهم، ثبت رویداد).
 * حذف نمی‌شود؛ باطل می‌شود.
 */
#[Fillable([
    'direction', 'type', 'letter_date', 'letter_template_id', 'recipient', 'organization', 'subject', 'body',
    'student_id', 'staff_id', 'external_number', 'received_on', 'needs_response', 'reply_to_id',
])]
class Letter extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => LetterDirection::class,
            'status' => LetterStatus::class,
            'type' => LetterType::class,
            'letter_date' => 'date',
            'received_on' => 'date',
            'needs_response' => 'boolean',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id')->where('status', '!=', LetterStatus::Cancelled->value);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LetterAttachment::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === LetterStatus::Draft;
    }

    public function isCancelled(): bool
    {
        return $this->status === LetterStatus::Cancelled;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === LetterDirection::Outgoing;
    }

    /** وارده‌ای که جواب می‌خواهد و هنوز جواب صادر نشده */
    public function awaitingResponse(): bool
    {
        return ! $this->isOutgoing() && $this->needs_response && ! $this->isCancelled() && ! $this->replies()->where('status', LetterStatus::Issued)->exists();
    }

    public function displayNumber(): string
    {
        return $this->number ? Digits::toPersian($this->number) : '—';
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return PersianText::whereMatches($query, ['subject', 'recipient', 'organization', 'number', 'external_number'], $term);
    }
}
