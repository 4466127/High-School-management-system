{{-- $subject (Subject|null), $own, $prefix --}}
<div class="row">
    <x-form.input class="col-8 mb-3" :id="$prefix.'_name'" name="name" :label="__('academics.subject_name')" :value="$subject?->name" required maxlength="100" :placeholder="__('academics.subject_name_ph')" :use-old="$own" />
    <x-form.input class="col-4 mb-3" :id="$prefix.'_code'" name="code" :label="__('academics.subject_code')" :value="$subject?->code" ltr maxlength="20" :placeholder="__('academics.subject_code_ph')" :use-old="$own" />
</div>
<input type="hidden" name="is_active" value="0">
<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $prefix }}_active" @checked($own ? old('is_active') : ($subject?->is_active ?? true))>
    <label class="form-check-label" for="{{ $prefix }}_active">{{ __('academics.subject_active') }}</label>
</div>
