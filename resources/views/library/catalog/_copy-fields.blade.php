{{--
    خانه‌های مشخصات جلد (ثبت کتاب، افزودن جلد، ویرایش جلد).
    $prefix: پیشوند id؛ $withCount: شمار جلد + نمبر ثبت اول (افزودن)؛ $copy: جلدی که ویرایش می‌شود؛ $open: old() این فرم را نشان بده.
--}}
@php
    $t = 'library.';
    $copy ??= null;
    $withCount ??= false;
    $val = fn (string $field, $default) => $open ? old($field, $default) : $default;
@endphp
<div class="row">
    @if ($withCount)
        <x-form.input class="col-6 col-md-3" :id="$prefix.'-copies'" name="copies" :label="__($t.'copies_count')" :value="$val('copies', 1)" inputmode="numeric" required :use-old="$open" :help="__($t.'help.copies_count')" />
        <x-form.input class="col-6 col-md-3" :id="$prefix.'-start'" name="start_accession" :label="__($t.'start_accession')" :value="null" maxlength="30" :use-old="$open"
                      :placeholder="__($t.'ph.start_accession', ['next' => \App\Support\Digits::toPersian((string) ($nextAccession ?? '1'))])" :help="__($t.'help.start_accession')" />
    @else
        <x-form.input class="col-6 col-md-3" :id="$prefix.'-acc'" name="accession_no" :label="__($t.'accession_no')" :value="$copy?->accession_no" required maxlength="30" :use-old="$open" />
    @endif
    <div class="col-6 col-md-3 mb-3">
        <label class="form-label" for="{{ $prefix }}-source">{{ __($t.'source') }}</label>
        <select name="source" id="{{ $prefix }}-source" class="form-select">
            @foreach (\App\Enums\CopySource::options() as $v => $l)<option value="{{ $v }}" @selected($val('source', $copy?->source?->value ?? 'purchase') === $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <label class="form-label" for="{{ $prefix }}-cond">{{ __($t.'condition') }}</label>
        <select name="condition" id="{{ $prefix }}-cond" class="form-select">
            @foreach (\App\Enums\CopyCondition::options() as $v => $l)<option value="{{ $v }}" @selected($val('condition', $copy?->condition?->value ?? 'new') === $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <x-jalali-date class="col-6 col-md-4" :id="$prefix.'-acq'" name="acquired_on" :label="__($t.'acquired_on')" :value="$copy?->acquired_on" :use-old="$open" :help="__($t.'help.acquired_on')" />
    <x-form.input class="col-6 col-md-4" :id="$prefix.'-price'" name="price" :label="__($t.'price')" :value="$copy?->price !== null && $copy ? \App\Support\Money::plain($copy->price) : null" inputmode="decimal" :use-old="$open" :placeholder="__($t.'ph.price')" :help="__($t.'help.price')" />
    <x-form.input class="col-md-4" :id="$prefix.'-cnotes'" name="copy_notes" :label="__($t.'copy_notes')" :value="$copy?->notes" maxlength="300" :use-old="$open" :placeholder="__($t.'ph.copy_notes')" />
</div>
