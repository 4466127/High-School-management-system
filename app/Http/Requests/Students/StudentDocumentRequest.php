<?php

namespace App\Http\Requests\Students;

use App\Enums\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => __('students.document_title'), 'file' => __('students.document_file'), 'category' => __('students.document_category')];
    }
}
