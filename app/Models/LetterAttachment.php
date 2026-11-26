<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ضمیمه‌ی مکتوب (اسکن مکتوب امضاشده، سند همراه). فایل در storage/app/private/letters. */
#[Fillable(['title', 'file_path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class LetterAttachment extends Model
{
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}
