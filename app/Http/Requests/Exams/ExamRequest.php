<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamType;
use App\Rules\ValidJalaliDate;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * ساخت و ویرایش امتحان. نمره‌ی کامل، وزن و «در نتیجه حساب شود» اگر خالی بماند از نوع امتحان گرفته می‌شود.
 * counts_in_final: '' = مطابق نوع، '1' = بلی، '0' = نخیر.
 */
class ExamRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['start_date', 'end_date'];
    }

    private function yearId(): ?int
    {
        $exam = $this->route('exam');

        return $exam instanceof Exam ? $exam->academic_year_id : AcademicYear::current()?->id;
    }

    public function rules(): array
    {
        $exam = $this->route('exam');

        return [
            'exam_type_id' => ['required', 'integer', Rule::exists('exam_types', 'id')],
            'academic_term_id' => ['nullable', 'integer', Rule::exists('academic_terms', 'id')->where('academic_year_id', $this->yearId())],
            'name' => ['required', 'string', 'max:150', Rule::unique('exams', 'name')->where('academic_year_id', $this->yearId())->ignore($exam instanceof Exam ? $exam->id : null)],
            'start_date' => ['nullable', new ValidJalaliDate],
            'end_date' => ['nullable', new ValidJalaliDate],
            'max_mark' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'counts_in_final' => ['nullable', Rule::in(['', '0', '1'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $start = JalaliDate::parse($this->input('start_date'));
            $end = JalaliDate::parse($this->input('end_date'));
            if ($start && $end && $end->lt($start)) {
                $validator->errors()->add('end_date', __('academics.end_before_start'));
            }
            if ($validator->errors()->isEmpty()) {
                $data = $this->examData();
                if ($data['counts_in_final'] && $data['weight'] <= 0) {
                    $validator->errors()->add('weight', __('exams.weight_required'));
                }
            }
        }];
    }

    /** مقدارهای نهایی برای ذخیره (خالی‌ها از نوع امتحان) */
    public function examData(): array
    {
        $data = $this->validated();
        $type = ExamType::findOrFail($data['exam_type_id']);

        return [
            'exam_type_id' => $type->id,
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'name' => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'max_mark' => (float) ($data['max_mark'] ?? $type->default_max_mark),
            'weight' => (float) ($data['weight'] ?? $type->default_weight),
            'counts_in_final' => ($data['counts_in_final'] ?? '') === '' ? $type->counts_in_final : $data['counts_in_final'] === '1',
        ];
    }

    public function attributes(): array
    {
        return __('exams.attributes');
    }
}
