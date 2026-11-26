<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'category', 'file_path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class StudentDocument extends Model
{
    protected function casts(): array
    {
        return ['category' => DocumentCategory::class];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
