<?php

namespace App\Http\Requests\Letters;

use App\Enums\AnnouncementAudience;
use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** ساخت یا ویرایش اعلان (مسیر با can:announcements.manage). */
class AnnouncementRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_pinned' => $this->boolean('is_pinned'),
            'publish' => $this->boolean('publish'),
            'remove_attachment' => $this->boolean('remove_attachment'),
        ]);
    }

    protected function jalaliDateFields(): array
    {
        return ['expires_on'];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'is_pinned' => ['boolean'],
            'publish' => ['boolean'],
            'expires_on' => ['nullable', new ValidJalaliDate],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'remove_attachment' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return __('announcements.attributes');
    }
}
