<?php

namespace App\Http\Requests\Library;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Services\LibraryService;
use App\Support\Digits;
use Illuminate\Foundation\Http\FormRequest;

/** افزودن جلد (POST: copies + start_accession) یا ویرایش یک جلد (PUT: accession_no). مسیر با can:library.manage. */
class CopyRequest extends FormRequest
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
        if ($this->filled('copies')) {
            $this->merge(['copies' => Digits::toLatin((string) $this->input('copies'))]);
        }
    }

    public function rules(): array
    {
        return array_merge($this->copyRules(), $this->isMethod('POST')
            ? ['copies' => ['required', 'integer', 'min:1', 'max:'.LibraryService::MAX_COPIES], 'start_accession' => ['nullable', 'string', 'max:30']]
            : ['accession_no' => ['required', 'string', 'max:30']]);
    }

    public function attributes(): array
    {
        return __('library.attributes');
    }

    /** @return array<string, mixed> */
    public function copyData(): array
    {
        return BookRequest::copyFields($this->validated());
    }
}
