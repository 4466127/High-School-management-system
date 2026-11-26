{{-- خانه‌های مکتوب صادره (پیش‌نویس). $letter، $staffList --}}
<input type="hidden" name="letter_template_id" value="{{ old('letter_template_id', $letter->letter_template_id) }}">
<input type="hidden" name="reply_to_id" value="{{ old('reply_to_id', $letter->reply_to_id) }}">
<input type="hidden" name="student_id" value="{{ old('student_id', $letter->student_id) }}">

<div class="row">
    <x-form.select class="col-md-4 mb-3" name="type" :label="__('letters.type')" :options="\App\Enums\LetterType::options()" :value="$letter->type?->value ?? $letter->type" required />
    <div class="col-md-4 mb-3"><x-jalali-date name="letter_date" :label="__('letters.letter_date')" :value="$letter->letter_date" required /></div>
    <x-form.select class="col-md-4 mb-3" name="staff_id" :label="__('letters.staff')" :options="$staffList->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" :value="$letter->staff_id" :placeholder="'—'" />
    <x-form.input class="col-md-6 mb-3" name="recipient" :label="__('letters.recipient')" :value="$letter->recipient" maxlength="200" />
    <x-form.input class="col-md-6 mb-3" name="organization" :label="__('letters.organization')" :value="$letter->organization" maxlength="200" />
</div>
<x-form.input name="subject" :label="__('letters.subject')" :value="$letter->subject" required maxlength="255" />
<x-form.textarea name="body" :label="__('letters.body')" :value="$letter->body" rows="14" required :help="__('letters.body_hint')" />
