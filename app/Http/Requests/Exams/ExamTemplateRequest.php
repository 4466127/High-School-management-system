<?php

namespace App\Http\Requests\Exams;

use App\Models\GradeLevel;
use App\Support\ExamTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** قالب کارنامه و ترانسکریپت (مسیر با can:exams.manage بسته است). */
class ExamTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $switches = [];
        foreach (ExamTemplate::SWITCHES as $key) {
            $switches[$key] = $this->boolean($key);
        }
        $this->merge($switches + ['transcript_grades' => array_values(array_filter((array) $this->input('transcript_grades', [])))]);
    }

    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:150'];

        return [
            'reportcard_title' => $text,
            'reportcard_subtitle' => $text,
            'reportcard_signature_1' => ['nullable', 'string', 'max:100'],
            'reportcard_signature_2' => ['nullable', 'string', 'max:100'],
            'reportcard_signature_3' => ['nullable', 'string', 'max:100'],
            'reportcard_footer' => ['nullable', 'string', 'max:500'],
            'transcript_title' => $text,
            'transcript_signature_1' => ['nullable', 'string', 'max:100'],
            'transcript_signature_2' => ['nullable', 'string', 'max:100'],
            'transcript_footer' => ['nullable', 'string', 'max:500'],
            'transcript_grades' => ['array', 'max:6'],
            'transcript_grades.*' => ['integer', Rule::exists(GradeLevel::class, 'id')],
        ] + array_fill_keys(ExamTemplate::SWITCHES, ['boolean']);
    }

    public function attributes(): array
    {
        return __('exam_templates.attributes');
    }

    /** @return array<string, string> کلید تنظیمات ← مقدار */
    public function settings(): array
    {
        $data = $this->validated();
        $out = [];
        foreach ([...ExamTemplate::REPORT_CARD_KEYS, ...ExamTemplate::TRANSCRIPT_KEYS] as $key) {
            $out[$key] = in_array($key, ExamTemplate::SWITCHES, true) ? ($data[$key] ? '1' : '0') : trim((string) ($data[$key] ?? ''));
        }
        $out['transcript_grade_ids'] = implode(',', array_map('intval', $data['transcript_grades'] ?? []));

        return $out;
    }
}
